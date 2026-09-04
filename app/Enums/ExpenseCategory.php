<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case BahanBaku = 'bahan_baku';
    case Kemasan = 'kemasan';
    case Transport = 'transport';
    case Operasional = 'operasional';
    case Gaji = 'gaji';
    case LainLain = 'lain_lain';

    public function label(): string
    {
        return match ($this) {
            self::BahanBaku => 'Bahan Baku',
            self::Kemasan => 'Kemasan',
            self::Transport => 'Transport-Bensin',
            self::Operasional => 'Listrik-Air-Pulsa',
            self::Gaji => 'Gaji',
            self::LainLain => 'Lain-lain',
        };
    }

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
