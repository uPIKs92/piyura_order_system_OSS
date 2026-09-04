<?php

namespace App\Services;

use App\Models\Order;
use App\Support\TenantSettings;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Mayar dynamic QRIS integration (https://api.mayar.id).
 *
 * Flow: createQR amount -> data.url (direct PNG) + data.amount. No link id is
 * returned, so webhook matching relies on amount + tenant heuristic and the
 * webhook payload's transaction id is used as an idempotency key.
 */
class MayarService
{
    public function __construct(private TenantSettings $settings) {}

    public static function forTenant(int $tenantId): self
    {
        return new self(TenantSettings::for($tenantId));
    }

    public function enabled(): bool
    {
        return $this->settings->mayarEnabled();
    }

    /**
     * Resolve a QRIS PNG for the order's current remaining amount, reusing a
     * cached QR when the amount has not changed since the last call.
     *
     * @return array{url: string, amount: float}
     */
    public function qrCodeForOrder(Order $order): array
    {
        $remaining = (float) $order->remaining_amount;

        if (
            $order->mayar_qr_url
            && $order->mayar_amount !== null
            && (float) $order->mayar_amount === $remaining
        ) {
            return ['url' => $order->mayar_qr_url, 'amount' => $remaining];
        }

        $qr = $this->createQrCode($remaining);

        $order->update([
            'mayar_qr_url' => $qr['url'],
            'mayar_amount' => $order->remaining_amount,
        ]);

        return $qr;
    }

    /**
     * Create a fresh dynamic QRIS for an exact amount.
     *
     * @return array{url: string, amount: float}
     *
     * @throws RuntimeException when the API key is missing or the request fails.
     */
    public function createQrCode(float $amount): array
    {
        $apiKey = $this->settings->mayarApiKey();
        if (! $apiKey) {
            throw new RuntimeException('Mayar API key is not configured.');
        }

        $amountInt = (int) round($amount);
        if ($amountInt <= 0) {
            throw new RuntimeException('Amount must be greater than zero.');
        }

        $response = Http::withToken($apiKey)
            ->timeout(20)
            ->post($this->baseUrl().'/hl/v2/qr-codes/create', [
                'amount' => $amountInt,
            ]);

        if (! $response->ok()) {
            throw new RuntimeException('Mayar QR create failed (HTTP '.$response->status().').');
        }

        $data = $response->json('data');
        $url = $data['url'] ?? null;
        if (! is_string($url) || $url === '') {
            throw new RuntimeException('Mayar QR response missing data.url.');
        }

        return [
            'url' => $url,
            'amount' => isset($data['amount']) ? (float) $data['amount'] : (float) $amountInt,
        ];
    }

    /**
     * Lightweight connectivity + credential check (no side effects).
     */
    public function healthCheck(): bool
    {
        $apiKey = $this->settings->mayarApiKey();
        if (! $apiKey) {
            return false;
        }

        return Http::withToken($apiKey)
            ->timeout(15)
            ->get($this->baseUrl().'/hl/v2/qr-codes/static')
            ->ok();
    }

    protected function baseUrl(): string
    {
        return (string) config('services.mayar.base_url', 'https://api.mayar.id');
    }
}
