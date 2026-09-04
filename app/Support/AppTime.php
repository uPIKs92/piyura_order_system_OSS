<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * WIB (Asia/Jakarta) business clock.
 *
 * Storage stays UTC (config/app.php 'timezone' => 'UTC'), but user-facing
 * business dates — invoice/receipt numbers, default order dates, report day
 * boundaries, printed-at stamps — must follow WIB, the shop's timezone.
 * Route those reads through this helper instead of bare now()/today(),
 * which return UTC and are wrong for WIB between 00:00 and 06:59.
 */
class AppTime
{
    public const TZ = 'Asia/Jakarta';

    public static function now(): Carbon
    {
        return now(self::TZ);
    }

    public static function today(): Carbon
    {
        return today(self::TZ);
    }

    public static function toDateString(): string
    {
        return self::now()->toDateString();
    }

    /**
     * UTC instant of 00:00:00 WIB on the given Y-m-d — index-friendly lower
     * bound for filtering UTC datetime columns by WIB day.
     */
    public static function dayStartUtc(string $date): string
    {
        return Carbon::parse($date, self::TZ)->startOfDay()->utc()->format('Y-m-d H:i:s');
    }

    /**
     * UTC instant of 23:59:59 WIB on the given Y-m-d — index-friendly upper
     * bound for filtering UTC datetime columns by WIB day.
     */
    public static function dayEndUtc(string $date): string
    {
        return Carbon::parse($date, self::TZ)->endOfDay()->utc()->format('Y-m-d H:i:s');
    }
}
