<?php

namespace App\Support;

class ExpirySettings
{
    public static function alertDays(): int
    {
        if ($settings = TenantSettings::tryFor()) {
            return $settings->expiryAlertDays();
        }

        return (int) config('inventory.expiry_alert_days', 30);
    }

    public static function update(int $days): void
    {
        TenantSettings::for()->updateExpiryAlertDays($days);
    }

    public static function toArray(): array
    {
        if ($settings = TenantSettings::tryFor()) {
            return $settings->expiryToArray();
        }

        return [
            'alert_days' => self::alertDays(),
        ];
    }
}
