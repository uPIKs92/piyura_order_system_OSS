<?php

namespace App\Support;

class PpnSettings
{
    public static function enabled(): bool
    {
        if ($settings = TenantSettings::tryFor()) {
            return $settings->ppnEnabled();
        }

        return (bool) config('ppn.enabled', true);
    }

    public static function percentage(): float
    {
        if ($settings = TenantSettings::tryFor()) {
            return $settings->ppnPercentage();
        }

        return (float) config('ppn.percentage', 11);
    }

    public static function update(bool $enabled, float $percentage): void
    {
        TenantSettings::for()->updatePpn($enabled, $percentage);
    }

    public static function toArray(): array
    {
        if ($settings = TenantSettings::tryFor()) {
            return $settings->ppnToArray();
        }

        return [
            'enabled' => self::enabled(),
            'percentage' => self::percentage(),
        ];
    }
}
