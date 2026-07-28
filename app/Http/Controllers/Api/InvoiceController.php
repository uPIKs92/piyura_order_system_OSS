<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

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
        $order->load(['items.product', 'items.productUnit', 'payments', 'user', 'tenant']);
        $tenant = $order->tenant ?? $order->user?->tenant;

        $data = [
            'order' => $order,
            'tenant' => $tenant,
            'logoDataUri' => $this->logoDataUri($tenant?->logo_path),
            'showPlatformCredit' => config('branding.show_platform_credit_on_invoice'),
            'platformName' => config('branding.platform_name'),
            'appName' => config('branding.app_name'),
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

    private function logoDataUri(?string $logoPath): ?string
    {
        if (! $logoPath || ! Storage::disk('public')->exists($logoPath)) {
            return null;
        }

        $contents = Storage::disk('public')->get($logoPath);
        $mime = Storage::disk('public')->mimeType($logoPath) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
