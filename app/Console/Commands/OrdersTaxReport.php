<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\ReportService;
use App\Support\AppTime;
use Illuminate\Console\Command;

class OrdersTaxReport extends Command
{
    protected $signature = 'orders:tax-report
                            {--year= : Tahun laporan (default: tahun berjalan)}
                            {--tenant= : Slug atau ID tenant (kosongkan untuk semua tenant)}
                            {--format=table : Output format: table atau csv}';

    protected $description = 'Laporan pajak (PPN) tahunan per tenant';

    public function handle(ReportService $reportService): int
    {
        $year = (int) ($this->option('year') ?: AppTime::now()->year);
        $format = $this->option('format');

        if (! in_array($format, ['table', 'csv'], true)) {
            $this->error("Format tidak valid. Gunakan 'table' atau 'csv'.");

            return self::FAILURE;
        }

        $rows = [];

        if ($tenantOpt = $this->option('tenant')) {
            $tenant = Tenant::where('id', $tenantOpt)
                ->orWhere('slug', $tenantOpt)
                ->first();

            if (! $tenant) {
                $this->error("Tenant '{$tenantOpt}' tidak ditemukan.");

                return self::FAILURE;
            }

            $rows[] = $this->reportForTenant($reportService, $tenant, $year);
        } else {
            foreach (Tenant::orderBy('name')->get() as $tenant) {
                try {
                    $rows[] = $this->reportForTenant($reportService, $tenant, $year);
                } catch (\Throwable $e) {
                    report($e);
                    $rows[] = [
                        'tenant_name' => $tenant->name,
                        'tenant_slug' => $tenant->slug,
                        'year' => $year,
                        'error' => 'ERROR: '.$e->getMessage(),
                    ];
                }
            }
        }

        $rows = array_filter($rows, fn ($r) => $r !== null);

        if (empty($rows)) {
            $this->warn("Tidak ada data untuk tahun {$year}.");

            return self::SUCCESS;
        }

        if ($format === 'csv') {
            $this->outputCsv($rows);
        } else {
            $this->table(
                ['Tenant', 'Slug', 'Tahun', 'Total Orders', 'Total Revenue', 'Total PPN'],
                array_map(fn ($r) => [
                    $r['tenant_name'],
                    $r['tenant_slug'],
                    $r['year'],
                    $r['error'] ?? $r['total_orders'],
                    isset($r['error']) ? '-' : number_format((float) $r['total_revenue'], 0, ',', '.'),
                    isset($r['error']) ? '-' : number_format((float) $r['total_ppn'], 0, ',', '.'),
                ], $rows),
            );

            $grandRevenue = array_sum(array_map(fn ($r) => (float) ($r['total_revenue'] ?? 0), $rows));
            $grandPpn = array_sum(array_map(fn ($r) => (float) ($r['total_ppn'] ?? 0), $rows));

            $this->newLine();
            $this->info("Total Revenue (semua tenant): Rp " . number_format($grandRevenue, 0, ',', '.'));
            $this->info("Total PPN (semua tenant):     Rp " . number_format($grandPpn, 0, ',', '.'));
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function reportForTenant(ReportService $reportService, Tenant $tenant, int $year): ?array
    {
        app()->instance('currentTenantId', $tenant->id);

        $data = $reportService->taxReport($year);

        if ($data['totals']['orders'] === 0) {
            return null;
        }

        return [
            'tenant_name' => $tenant->name,
            'tenant_slug' => $tenant->slug,
            'year' => $data['year'],
            'total_orders' => $data['totals']['orders'],
            'total_revenue' => $data['totals']['omzet'],
            'total_ppn' => $data['totals']['ppn'],
        ];
    }

    /**
     * @param  array<int, array<string,mixed>>  $rows
     */
    private function outputCsv(array $rows): void
    {
        $this->line('tenant_name,tenant_slug,year,total_orders,total_revenue,total_ppn');

        foreach ($rows as $r) {
            if (isset($r['error'])) {
                $this->line(sprintf(
                    '%s,%s,%d,ERROR,,%s',
                    str_replace(',', '', $r['tenant_name']),
                    $r['tenant_slug'],
                    $r['year'],
                    str_replace(',', '', $r['error']),
                ));

                continue;
            }

            $this->line(sprintf(
                '%s,%s,%d,%d,%s,%s',
                str_replace(',', '', $r['tenant_name']),
                $r['tenant_slug'],
                $r['year'],
                $r['total_orders'],
                $r['total_revenue'],
                $r['total_ppn'],
            ));
        }
    }
}
