<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSettingsIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ppn_settings_are_isolated_per_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        TenantSettings::for($tenantA->id)->updatePpn(true, 11);
        TenantSettings::for($tenantB->id)->updatePpn(false, 5);

        $this->assertTrue(TenantSettings::for($tenantA->id)->ppnEnabled());
        $this->assertEquals(11.0, TenantSettings::for($tenantA->id)->ppnPercentage());
        $this->assertFalse(TenantSettings::for($tenantB->id)->ppnEnabled());
        $this->assertEquals(5.0, TenantSettings::for($tenantB->id)->ppnPercentage());
    }

    public function test_owner_updates_ppn_for_own_tenant_only(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $ownerA = User::factory()->owner()->create(['tenant_id' => $tenantA->id]);
        $token = $ownerA->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/settings/ppn', ['enabled' => false, 'percentage' => 8])
            ->assertOk()
            ->assertJson(['enabled' => false, 'percentage' => 8]);

        $this->assertFalse(TenantSettings::for($tenantA->id)->ppnEnabled());
        $this->assertTrue(TenantSettings::for($tenantB->id)->ppnEnabled());
    }
}
