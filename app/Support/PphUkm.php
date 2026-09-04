<?php

namespace App\Support;

class PphUkm
{
    public const MODE_NON_PKP = 'umkm_non_pkp';

    public const MODE_PKP_22 = 'umkm_pkp_22';

    public static function isValidMode(string $mode): bool
    {
        return in_array($mode, [self::MODE_NON_PKP, self::MODE_PKP_22], true);
    }

    /**
     * PPh Final UMKM non-PKP atas omzet setahun kumulatif (PP 55/2022 jo. PP 28/2025):
     * 0,5% untuk omzet sampai tier 1, 12% final untuk kelebihannya.
     */
    public static function annualTax(float $cumulativeOmzet): float
    {
        $tier1 = (float) config('pajak.omzet_tier1');
        $rate1 = (float) config('pajak.rate_non_pkp_1');
        $rate2 = (float) config('pajak.rate_non_pkp_2');

        $withinTier = min($cumulativeOmzet, $tier1);
        $excess = max(0.0, $cumulativeOmzet - $tier1);

        return $withinTier * ($rate1 / 100) + $excess * ($rate2 / 100);
    }

    /**
     * PPh satu masa pajak: non-PKP = f(kumulatif s.d. masa ini) - f(kumulatif s.d.
     * masa lalu); PKP (PPh 22) = 2,5% x omzet masa berjalan.
     */
    public static function monthlyTax(float $monthlyOmzet, float $priorCumulativeOmzet, string $mode): float
    {
        if ($mode === self::MODE_PKP_22) {
            return round($monthlyOmzet * ((float) config('pajak.rate_pkp_22') / 100), 2);
        }

        $after = self::annualTax($priorCumulativeOmzet + $monthlyOmzet);
        $before = self::annualTax($priorCumulativeOmzet);

        return round($after - $before, 2);
    }

    /**
     * @param  array<int, float>  $monthlyOmzet  omzet per masa (urut masa, index 0 = masa 1)
     * @return array<int, float>  PPh per masa, dibulatkan 2 desimal per masa
     */
    public static function monthlySeries(array $monthlyOmzet, string $mode): array
    {
        $series = [];
        $cumulative = 0.0;

        foreach ($monthlyOmzet as $omzet) {
            $series[] = self::monthlyTax((float) $omzet, $cumulative, $mode);
            $cumulative += (float) $omzet;
        }

        return $series;
    }
}
