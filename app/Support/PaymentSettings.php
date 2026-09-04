<?php

namespace App\Support;

class PaymentSettings
{
    public static function bank(): array
    {
        if ($settings = TenantSettings::tryFor()) {
            return [
                'bank_name' => $settings->bankName(),
                'bank_account_name' => $settings->bankAccountName(),
                'bank_account_number' => $settings->bankAccountNumber(),
            ];
        }

        return [
            'bank_name' => null,
            'bank_account_name' => null,
            'bank_account_number' => null,
        ];
    }

    public static function qrisImageUrl(): ?string
    {
        if ($settings = TenantSettings::tryFor()) {
            return $settings->qrisImageUrl();
        }

        return null;
    }

    public static function mayarEnabled(): bool
    {
        if ($settings = TenantSettings::tryFor()) {
            return $settings->mayarEnabled();
        }

        return false;
    }

    public static function update(array $data): void
    {
        TenantSettings::for()->updatePayment($data);
    }

    public static function toArray(): array
    {
        if ($settings = TenantSettings::tryFor()) {
            return $settings->paymentToArray();
        }

        return [
            'bank_name' => null,
            'bank_account_name' => null,
            'bank_account_number' => null,
            'qris_image_url' => null,
            'mayar_enabled' => false,
        ];
    }
}
