<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Tenant;
use App\Support\PaymentSettings;
use App\Support\TenantSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InvoiceController extends Controller
{
    public function show(Order $order): Response
    {
        $this->authorize('view', $order);

        return $this->renderPdf($order, true);
    }

    public function preview(Order $order): Response
    {
        $this->authorize('view', $order);

        return $this->renderPdf($order, false);
    }

    private function renderPdf(Order $order, bool $download): Response
    {
        $order->load(['items.product', 'items.productUnit', 'items.batchAllocations.productBatch', 'payments', 'user', 'tenant']);
        $tenant = $order->tenant ?? $order->user?->tenant;

        [$qrisDataUri, $qrisPendingNote] = $this->resolveQris($order, $tenant);

        $data = [
            'order' => $order,
            'tenant' => $tenant,
            'logoDataUri' => $this->logoDataUri($tenant?->logo_path),
            'showPlatformCredit' => config('branding.show_platform_credit_on_invoice'),
            'platformName' => config('branding.platform_name'),
            'appName' => config('branding.app_name'),
            'bank' => PaymentSettings::bank(),
            'qrisDataUri' => $qrisDataUri,
            'qrisPendingNote' => $qrisPendingNote,
        ];

        $pdf = Pdf::loadView('pdf.invoice', $data);

        if ($download) {
            return $pdf->download("invoice-{$order->invoice_no}.pdf");
        }

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="invoice-'.$order->invoice_no.'.pdf"',
        ]);
    }

    /**
     * Static QRIS image wins. Otherwise, when Mayar dynamic QRIS is enabled,
     * reuse the order's cached QR PNG only if its amount still matches the
     * remaining balance; no Mayar API calls and no order mutation happen here.
     * A pending note is shown when Mayar is enabled but no QR is embeddable.
     *
     * @return array{0: ?string, 1: bool}
     */
    private function resolveQris(Order $order, ?Tenant $tenant): array
    {
        $settings = TenantSettings::tryFor($tenant?->id);

        if ($path = $settings?->qrisImagePath()) {
            return [$this->imageDataUri($path), false];
        }

        if (! $settings?->mayarEnabled()) {
            return [null, false];
        }

        if ($order->mayar_qr_url && (float) $order->mayar_amount === (float) $order->remaining_amount) {
            $host = strtolower((string) parse_url((string) $order->mayar_qr_url, PHP_URL_HOST));
            $isMayarHost = $host === 'api.mayar.id' || str_ends_with($host, '.mayar.id');

            if (! $isMayarHost) {
                return [null, true];
            }

            $qrisDataUri = $this->mayarQrDataUri((string) $order->mayar_qr_url);

            if ($qrisDataUri !== null) {
                return [$qrisDataUri, false];
            }
        }

        return [null, true];
    }

    /**
     * Mayar dynamic QR PNG as a data URI, cached ~5 minutes per URL so
     * back-to-back invoice renders cost one Mayar fetch. Dynamic codes
     * expire quickly, so failures and empty bodies are never cached and
     * always refetch on the next render.
     */
    private function mayarQrDataUri(string $url): ?string
    {
        $qr = Cache::remember(
            'qris:png:'.sha1($url),
            now()->addMinutes(5),
            fn (): ?array => $this->fetchMayarQr($url),
        );

        if ($qr === null) {
            return null;
        }

        return 'data:'.$qr['mime'].';base64,'.base64_encode($qr['body']);
    }

    /**
     * @return array{mime: string, body: string}|null
     */
    private function fetchMayarQr(string $url): ?array
    {
        try {
            $response = Http::timeout(10)->get($url);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful() || $response->body() === '') {
            return null;
        }

        $mime = strtok((string) $response->header('Content-Type'), ';') ?: 'image/png';

        return ['mime' => $mime, 'body' => $response->body()];
    }

    private function logoDataUri(?string $logoPath): ?string
    {
        return $this->imageDataUri($logoPath);
    }

    private function imageDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $contents = Storage::disk('public')->get($path);
        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
