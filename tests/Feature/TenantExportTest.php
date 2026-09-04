<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_contains_only_one_tenant_data(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'toko-a', 'name' => 'Toko A']);
        $tenantB = Tenant::factory()->create(['slug' => 'toko-b', 'name' => 'Toko B']);

        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

        Order::factory()->create(['tenant_id' => $tenantA->id, 'user_id' => $userA->id]);
        Order::factory()->create(['tenant_id' => $tenantB->id, 'user_id' => $userB->id]);

        Expense::factory()->create(['tenant_id' => $tenantA->id, 'amount' => 15000]);
        Expense::factory()->create(['tenant_id' => $tenantB->id, 'amount' => 99000]);

        $payload = app(TenantExporter::class)->export($tenantA);

        $this->assertSame('toko-a', $payload['tenant']['slug']);
        $this->assertCount(1, $payload['orders']);
        $this->assertSame($tenantA->id, $payload['orders'][0]['tenant_id']);
        $this->assertCount(1, $payload['expenses']);
        $this->assertSame($tenantA->id, $payload['expenses'][0]['tenant_id']);
        $this->assertSame('15000.00', $payload['expenses'][0]['amount']);
    }

    public function test_owner_can_export_via_api(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'toko-a']);
        $owner = User::factory()->owner()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        Order::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);

        $this->actingAs($owner)
            ->getJson('/api/tenant/export')
            ->assertOk()
            ->assertJsonPath('tenant.slug', 'toko-a')
            ->assertJsonCount(1, 'orders');
    }
}
