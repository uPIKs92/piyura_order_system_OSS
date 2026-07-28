<?php

namespace App\Support;

use App\Models\TenantSetting;
use Illuminate\Support\Facades\Crypt;

class TenantSettings
{
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
        $row = TenantSetting::query()
            ->where('tenant_id', $this->tenantId)
            ->where('key', $key)
            ->value('value');

        if ($row === null) {
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
    }

    public function forget(string $key): void
    {
        TenantSetting::query()
            ->where('tenant_id', $this->tenantId)
            ->where('key', $key)
            ->delete();
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
    }
}
