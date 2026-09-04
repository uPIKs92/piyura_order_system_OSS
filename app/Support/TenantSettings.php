<?php

namespace App\Support;

use App\Models\TenantSetting;
use App\Services\OrderService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use App\Support\SafeUrl;

class TenantSettings
{
    private static array $memo = [];

    private static ?\stdClass $miss = null;

    public function __construct(private int $tenantId) {}

    public static function for(?int $tenantId = null): self
    {
        $resolved = $tenantId;

        if (! $resolved && app()->bound('currentTenantId')) {
            $resolved = app('currentTenantId');
        }

        if (! $resolved) {
            throw new \RuntimeException('Tenant context is required for tenant settings.');
        }

        return new self((int) $resolved);
    }

    public static function tryFor(?int $tenantId = null): ?self
    {
        $resolved = $tenantId ?? (app()->bound('currentTenantId') ? app('currentTenantId') : null);

        return $resolved ? new self((int) $resolved) : null;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = $this->tenantId.'|'.$key;

        if (! array_key_exists($cacheKey, self::$memo)) {
            $row = TenantSetting::query()
                ->where('tenant_id', $this->tenantId)
                ->where('key', $key)
                ->value('value');

            self::$memo[$cacheKey] = $row === null ? self::miss() : $row;
        }

        $row = self::$memo[$cacheKey];

        if ($row instanceof \stdClass) {
            return $default;
        }

        $decoded = json_decode($row, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $row;
    }

    public function set(string $key, mixed $value): void
    {
        TenantSetting::query()->updateOrCreate(
            ['tenant_id' => $this->tenantId, 'key' => $key],
            ['value' => json_encode($value)],
        );

        self::flushMemo($this->tenantId);
    }

    public function forget(string $key): void
    {
        TenantSetting::query()
            ->where('tenant_id', $this->tenantId)
            ->where('key', $key)
            ->delete();

        self::flushMemo($this->tenantId);
    }

    public static function flushMemo(?int $tenantId = null): void
    {
        if ($tenantId === null) {
            self::$memo = [];

            return;
        }

        $prefix = $tenantId.'|';

        foreach (array_keys(self::$memo) as $cacheKey) {
            if (str_starts_with((string) $cacheKey, $prefix)) {
                unset(self::$memo[$cacheKey]);
            }
        }
    }

    private static function miss(): \stdClass
    {
        return self::$miss ??= new \stdClass();
    }

    public function ppnEnabled(): bool
    {
        return (bool) $this->get('ppn.enabled', config('ppn.enabled', true));
    }

    public function ppnPercentage(): float
    {
        return (float) $this->get('ppn.percentage', config('ppn.percentage', 11));
    }

    public function updatePpn(bool $enabled, float $percentage): void
    {
        $this->set('ppn.enabled', $enabled);
        $this->set('ppn.percentage', $percentage);
    }

    public function ppnToArray(): array
    {
        return [
            'enabled' => $this->ppnEnabled(),
            'percentage' => $this->ppnPercentage(),
        ];
    }

    public function pajakNpwp(): ?string
    {
        $value = $this->get('pajak.npwp');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function pphMode(): string
    {
        $mode = $this->get('pajak.pph_mode', 'umkm_non_pkp');

        return is_string($mode) && $mode !== '' ? $mode : 'umkm_non_pkp';
    }

    public function updatePajak(array $data): void
    {
        if (array_key_exists('npwp', $data)) {
            $this->set('pajak.npwp', $data['npwp']);
        }
        if (array_key_exists('pph_mode', $data)) {
            $this->set('pajak.pph_mode', $data['pph_mode']);
        }

        // Cached aggregates include tax-report output derived from pajak
        // settings, so changing them must invalidate those payloads too.
        OrderService::bumpAggregatesVersion($this->tenantId);
    }

    public function pajakToArray(): array
    {
        return [
            'npwp' => $this->pajakNpwp(),
            'pph_mode' => $this->pphMode(),
            'ppn' => $this->ppnToArray(),
        ];
    }

    // ---- Delivery (ongkir) ----

    public function deliveryFeeMode(): string
    {
        $mode = $this->get('delivery.fee_mode', 'per_km');

        return is_string($mode) && $mode !== '' ? $mode : 'per_km';
    }

    public function deliveryFeePerKm(): float
    {
        return (float) $this->get('delivery.fee_per_km', 5000);
    }

    public function deliveryMinFee(): float
    {
        return (float) $this->get('delivery.min_fee', 0);
    }

    public function deliveryFixedFee(): float
    {
        return (float) $this->get('delivery.fixed_fee', 10000);
    }

    public function deliveryOriginLat(): ?float
    {
        $value = $this->get('delivery.origin_lat');

        return is_numeric($value) ? (float) $value : null;
    }

    public function deliveryOriginLon(): ?float
    {
        $value = $this->get('delivery.origin_lon');

        return is_numeric($value) ? (float) $value : null;
    }

    public function setDeliveryOrigin(?float $lat, ?float $lon): void
    {
        if ($lat === null || $lon === null) {
            $this->forget('delivery.origin_lat');
            $this->forget('delivery.origin_lon');

            return;
        }

        $this->set('delivery.origin_lat', $lat);
        $this->set('delivery.origin_lon', $lon);
    }

    public function googleMapsApiKey(): ?string
    {
        $encrypted = $this->get('delivery.google_maps_api_key');
        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setGoogleMapsApiKey(string $key): void
    {
        $this->set('delivery.google_maps_api_key', Crypt::encryptString($key));
    }

    public function clearGoogleMapsApiKey(): void
    {
        $this->forget('delivery.google_maps_api_key');
    }

    public function updateDelivery(array $data): void
    {
        if (array_key_exists('fee_mode', $data)) {
            $this->set('delivery.fee_mode', $data['fee_mode']);
        }
        if (array_key_exists('fee_per_km', $data)) {
            $this->set('delivery.fee_per_km', (float) $data['fee_per_km']);
        }
        if (array_key_exists('min_fee', $data)) {
            $this->set('delivery.min_fee', (float) $data['min_fee']);
        }
        if (array_key_exists('fixed_fee', $data)) {
            $this->set('delivery.fixed_fee', (float) $data['fixed_fee']);
        }
        if (array_key_exists('google_maps_api_key', $data)) {
            $key = is_string($data['google_maps_api_key']) ? trim($data['google_maps_api_key']) : '';
            if ($key === '') {
                $this->clearGoogleMapsApiKey();
            } else {
                $this->setGoogleMapsApiKey($key);
            }
        }
    }

    public function deliveryToArray(): array
    {
        return [
            'fee_mode' => $this->deliveryFeeMode(),
            'fee_per_km' => $this->deliveryFeePerKm(),
            'min_fee' => $this->deliveryMinFee(),
            'fixed_fee' => $this->deliveryFixedFee(),
            'google_maps_configured' => $this->googleMapsApiKey() !== null,
        ];
    }

    public function expiryAlertDays(): int
    {
        return (int) $this->get('expiry.alert_days', config('inventory.expiry_alert_days', 30));
    }

    public function updateExpiryAlertDays(int $days): void
    {
        $this->set('expiry.alert_days', max(1, min(365, $days)));
    }

    public function expiryToArray(): array
    {
        return [
            'alert_days' => $this->expiryAlertDays(),
        ];
    }

    public function sheetsSyncEnabled(): bool
    {
        return (bool) $this->get('sheets.sync_enabled', false);
    }

    public function sheetsSpreadsheetId(): ?string
    {
        $id = $this->get('sheets.spreadsheet_id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function sheetsProductsTab(): string
    {
        $tab = $this->get('sheets.products_tab', config('google.defaults.products_tab', 'Products'));

        return is_string($tab) && $tab !== '' ? $tab : 'Products';
    }

    public function sheetsOrdersTab(): string
    {
        $tab = $this->get('sheets.orders_tab', config('google.defaults.orders_tab', 'Orders'));

        return is_string($tab) && $tab !== '' ? $tab : 'Orders';
    }

    public function sheetsReportingTab(): string
    {
        $tab = $this->get('sheets.reporting_tab', config('google.defaults.reporting_tab', 'Reporting'));

        return is_string($tab) && $tab !== '' ? $tab : 'Reporting';
    }

    public function googleConnected(): bool
    {
        return (bool) $this->get('google.connected', false) && filled($this->googleRefreshToken());
    }

    public function googleConnectedEmail(): ?string
    {
        $email = $this->get('google.connected_email');

        return is_string($email) && $email !== '' ? $email : null;
    }

    public function googleRefreshToken(): ?string
    {
        $encrypted = $this->get('google.refresh_token');
        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setGoogleConnection(string $refreshToken, ?string $email): void
    {
        $this->set('google.refresh_token', Crypt::encryptString($refreshToken));
        $this->set('google.connected', true);
        $this->set('google.connected_email', $email);
    }

    public function clearGoogleConnection(): void
    {
        $this->forget('google.refresh_token');
        $this->set('google.connected', false);
        $this->set('google.connected_email', null);
    }

    public function updateIntegrations(array $data): void
    {
        if (array_key_exists('sheets_sync_enabled', $data)) {
            $this->set('sheets.sync_enabled', (bool) $data['sheets_sync_enabled']);
        }

        if (array_key_exists('sheets_spreadsheet_id', $data)) {
            $this->set('sheets.spreadsheet_id', $data['sheets_spreadsheet_id']);
        }

        if (array_key_exists('sheets_products_tab', $data)) {
            $this->set('sheets.products_tab', $data['sheets_products_tab'] ?: config('google.defaults.products_tab', 'Products'));
        }

        if (array_key_exists('sheets_orders_tab', $data)) {
            $this->set('sheets.orders_tab', $data['sheets_orders_tab'] ?: config('google.defaults.orders_tab', 'Orders'));
        }

        if (array_key_exists('sheets_reporting_tab', $data)) {
            $this->set('sheets.reporting_tab', $data['sheets_reporting_tab'] ?: config('google.defaults.reporting_tab', 'Reporting'));
        }
    }

    public function integrationsToArray(): array
    {
        return [
            'sheets_sync_enabled' => $this->sheetsSyncEnabled(),
            'sheets_spreadsheet_id' => $this->sheetsSpreadsheetId(),
            'sheets_products_tab' => $this->sheetsProductsTab(),
            'sheets_orders_tab' => $this->sheetsOrdersTab(),
            'sheets_reporting_tab' => $this->sheetsReportingTab(),
            'google_connected' => $this->googleConnected(),
            'google_connected_email' => $this->googleConnectedEmail(),
        ];
    }

    public function sheetsImportEpoch(): int
    {
        return (int) $this->get('sheets.import_epoch', 1);
    }

    public function bumpSheetsImportEpoch(): int
    {
        $next = $this->sheetsImportEpoch() + 1;
        $this->set('sheets.import_epoch', $next);

        return $next;
    }

    // ---- Payment (bank, static QRIS, Mayar dynamic QRIS) ----

    public function bankName(): ?string
    {
        $value = $this->get('payment.bank_name');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function bankAccountName(): ?string
    {
        $value = $this->get('payment.bank_account_name');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function bankAccountNumber(): ?string
    {
        $value = $this->get('payment.bank_account_number');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function qrisImagePath(): ?string
    {
        $value = $this->get('payment.qris_image');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function setQrisImage(string $path): void
    {
        $this->set('payment.qris_image', $path);
    }

    public function clearQrisImage(): void
    {
        $this->forget('payment.qris_image');
    }

    public function mayarApiKey(): ?string
    {
        $encrypted = $this->get('payment.mayar_api_key');
        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setMayarApiKey(string $key): void
    {
        $this->set('payment.mayar_api_key', Crypt::encryptString($key));
    }

    public function clearMayarApiKey(): void
    {
        $this->forget('payment.mayar_api_key');
    }

    public function qrisImageUrl(): ?string
    {
        $path = $this->qrisImagePath();
        if (! $path) {
            return null;
        }

        return SafeUrl::sanitize(Storage::disk('public')->url($path));
    }

    /**
     * Mayar dynamic QRIS is used only when no static QRIS image is configured
     * and a Mayar API key has been stored.
     */
    public function mayarEnabled(): bool
    {
        return $this->qrisImagePath() === null && $this->mayarApiKey() !== null;
    }

    public function updatePayment(array $data): void
    {
        if (array_key_exists('bank_name', $data)) {
            $this->set('payment.bank_name', $data['bank_name']);
        }
        if (array_key_exists('bank_account_name', $data)) {
            $this->set('payment.bank_account_name', $data['bank_account_name']);
        }
        if (array_key_exists('bank_account_number', $data)) {
            $this->set('payment.bank_account_number', $data['bank_account_number']);
        }
        if (array_key_exists('mayar_api_key', $data)) {
            $key = is_string($data['mayar_api_key']) ? trim($data['mayar_api_key']) : '';
            if ($key === '') {
                $this->clearMayarApiKey();
            } else {
                $this->setMayarApiKey($key);
            }
        }
    }

    public function paymentToArray(): array
    {
        return [
            'bank_name' => $this->bankName(),
            'bank_account_name' => $this->bankAccountName(),
            'bank_account_number' => $this->bankAccountNumber(),
            'qris_image_url' => $this->qrisImageUrl(),
            'mayar_enabled' => $this->mayarEnabled(),
        ];
    }

    public static function seedDefaults(int $tenantId): void
    {
        $settings = new self($tenantId);
        $settings->updatePpn((bool) config('ppn.enabled', true), (float) config('ppn.percentage', 11));
        $settings->updateIntegrations([
            'sheets_sync_enabled' => false,
            'sheets_spreadsheet_id' => null,
            'sheets_products_tab' => config('google.defaults.products_tab', 'Products'),
            'sheets_orders_tab' => config('google.defaults.orders_tab', 'Orders'),
            'sheets_reporting_tab' => config('google.defaults.reporting_tab', 'Reporting'),
        ]);
        $settings->set('google.connected', false);
        $settings->set('google.connected_email', null);
        $settings->updatePayment([
            'bank_name' => null,
            'bank_account_name' => null,
            'bank_account_number' => null,
            'mayar_api_key' => null,
        ]);
        $settings->set('expiry.alert_days', (int) config('inventory.expiry_alert_days', 30));
        $settings->updateDelivery([
            'fee_mode' => 'per_km',
            'fee_per_km' => 5000,
            'min_fee' => 0,
            'fixed_fee' => 10000,
        ]);
        $settings->clearQrisImage();
    }
}
