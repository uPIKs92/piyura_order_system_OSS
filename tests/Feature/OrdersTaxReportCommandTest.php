<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OrdersTaxReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_outputs_table_for_all_tenants(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Test Toko']);
        $owner = User::factory()->owner()->create(['tenant_id' => $tenant->id]);

        $this->seedOrderForTenant($tenant, $owner, 2026, 50000, 5500);

        $this->artisan('orders:tax-report', ['--year' => 2026])
            ->assertSuccessful()
            ->expectsTable(
                ['Tenant', 'Slug', 'Tahun', 'Total Orders', 'Total Revenue', 'Total PPN'],
                [[
                    'Test Toko',
                    $tenant->slug,
                    '2026',
                    '1',
                    '50.000',
                    '5.500',
                ]],
            );
    }

    public function test_command_filters_by_tenant_slug(): void
    {
        $tenantA = Tenant::factory()->create(['name' => 'Toko A']);
        $ownerA = User::factory()->owner()->create(['tenant_id' => $tenantA->id]);
        $this->seedOrderForTenant($tenantA, $ownerA, 2026, 100000, 11000);

        $tenantB = Tenant::factory()->create(['name' => 'Toko B']);
        $ownerB = User::factory()->owner()->create(['tenant_id' => $tenantB->id]);
        $this->seedOrderForTenant($tenantB, $ownerB, 2026, 200000, 22000);

        $this->artisan('orders:tax-report', ['--tenant' => $tenantA->slug, '--year' => 2026])
            ->assertSuccessful()
            ->expectsOutputToContain('Toko A')
            ->doesntExpectOutputToContain('Toko B');
    }

    public function test_command_csv_format(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'CSV Toko']);
        $owner = User::factory()->owner()->create(['tenant_id' => $tenant->id]);
        $this->seedOrderForTenant($tenant, $owner, 2026, 75000, 8250);

        $this->artisan('orders:tax-report', ['--year' => 2026, '--format' => 'csv'])
            ->assertSuccessful()
            ->expectsOutputToContain('tenant_name,tenant_slug,year,total_orders,total_revenue,total_ppn')
            ->expectsOutputToContain('CSV Toko');
    }

    public function test_command_shows_no_data_message_when_empty(): void
    {
        $this->artisan('orders:tax-report', ['--year' => 1999])
            ->assertSuccessful()
            ->expectsOutputToContain('Tidak ada data untuk tahun 1999.');
    }

    public function test_command_fails_for_unknown_tenant(): void
    {
        $this->artisan('orders:tax-report', ['--tenant' => 'nonexistent'])
            ->assertFailed()
            ->expectsOutputToContain("Tenant 'nonexistent' tidak ditemukan.");
    }

    public function test_failing_tenant_does_not_abort_multi_tenant_csv(): void
    {
        $tenantA = Tenant::factory()->create(['name' => 'Good Toko']);
        $ownerA = User::factory()->owner()->create(['tenant_id' => $tenantA->id]);
        $this->seedOrderForTenant($tenantA, $ownerA, 2026, 100000, 11000);

        $tenantB = Tenant::factory()->create(['name' => 'Broken Toko']);
        User::factory()->owner()->create(['tenant_id' => $tenantB->id]);

        $real = $this->app->make(ReportService::class);
        $this->instance(ReportService::class, new class($tenantB->id, $real) extends ReportService {
            public function __construct(private int $failTenantId, private ReportService $real) {}

            public function taxReport(int $year): array
            {
                if ((int) app('currentTenantId') === $this->failTenantId) {
                    throw new \RuntimeException('laporan meledak');
                }

                return $this->real->taxReport($year);
            }
        });

        // PendingCommand's expectsOutputToContain cannot assert two
        // substrings carried by the SAME output line (Mockery hands the
        // doWrite call to the first matching expectation), and the error
        // row contains both the tenant name and ERROR — so assert on the
        // buffered output directly.
        $exit = Artisan::call('orders:tax-report', ['--year' => 2026, '--format' => 'csv']);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Good Toko', $output);
        $this->assertStringContainsString('11000', $output);
        $this->assertStringContainsString('Broken Toko', $output);
        $this->assertStringContainsString('ERROR', $output);
    }

    private function seedOrderForTenant(
        Tenant $tenant,
        User $owner,
        int $year,
        float $grandTotal,
        float $ppn,
    ): void {
        app()->instance('currentTenantId', $tenant->id);

        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $unit = $product->units()->first();

        Order::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'order_date' => "{$year}-06-15",
            'status' => 'selesai',
            'grand_total' => $grandTotal,
            'ppn_amount' => $ppn,
            'subtotal' => $grandTotal - $ppn,
        ]);

        app()->forgetInstance('currentTenantId');
    }
}
