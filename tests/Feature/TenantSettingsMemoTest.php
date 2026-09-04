<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Support\TenantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantSettingsMemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        TenantSettings::flushMemo();
    }

    protected function tearDown(): void
    {
        TenantSettings::flushMemo();

        parent::tearDown();
    }

    public function test_repeated_get_calls_for_same_key_query_database_once(): void
    {
        $tenant = Tenant::factory()->create();
        TenantSettings::for($tenant->id)->set('delivery.fee_per_km', 7000);

        DB::enableQueryLog();

        $settings = TenantSettings::for($tenant->id);
        $this->assertSame(7000.0, $settings->deliveryFeePerKm());
        $this->assertSame(7000.0, $settings->deliveryFeePerKm());
        $this->assertSame(7000, $settings->get('delivery.fee_per_km'));
        $this->assertNull($settings->get('delivery.missing_key'));
        $this->assertNull($settings->get('delivery.missing_key'));

        $settingQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_contains($query['query'], 'tenant_settings'))
            ->filter(fn (array $query) => in_array('delivery.fee_per_km', $query['bindings'], true))
            ->count();

        DB::disableQueryLog();

        $this->assertSame(1, $settingQueries);
    }

    public function test_write_then_read_returns_new_value(): void
    {
        $tenant = Tenant::factory()->create();

        $settings = TenantSettings::for($tenant->id);
        $this->assertSame(5000.0, $settings->deliveryFeePerKm());

        TenantSettings::for($tenant->id)->set('delivery.fee_per_km', 9000);

        $this->assertSame(9000.0, $settings->deliveryFeePerKm());
    }

    public function test_forget_invalidates_memo(): void
    {
        $tenant = Tenant::factory()->create();

        $settings = TenantSettings::for($tenant->id);
        $this->assertSame(5000.0, $settings->deliveryFeePerKm());

        TenantSettings::for($tenant->id)->forget('delivery.fee_per_km');

        $this->assertSame(5000.0, $settings->deliveryFeePerKm());
    }

    public function test_memo_is_not_shared_between_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        TenantSettings::for($tenantA->id)->set('delivery.fee_per_km', 7000);
        TenantSettings::for($tenantB->id)->set('delivery.fee_per_km', 3000);

        $this->assertSame(7000.0, TenantSettings::for($tenantA->id)->deliveryFeePerKm());
        $this->assertSame(3000.0, TenantSettings::for($tenantB->id)->deliveryFeePerKm());

        TenantSettings::for($tenantA->id)->set('delivery.fee_per_km', 8000);

        $this->assertSame(8000.0, TenantSettings::for($tenantA->id)->deliveryFeePerKm());
        $this->assertSame(3000.0, TenantSettings::for($tenantB->id)->deliveryFeePerKm());
    }
}
