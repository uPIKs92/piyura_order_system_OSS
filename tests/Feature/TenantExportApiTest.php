<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantExportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
        $this->staff = User::factory()->create(['tenant_id' => $this->owner->tenant_id]);
    }

    public function test_owner_can_export_tenant_data(): void
    {
        $this->actingAs($this->owner)
            ->getJson('/api/tenant/export')
            ->assertOk()
            ->assertJsonStructure(['tenant', 'users', 'orders', 'products', 'categories']);
    }

    public function test_staff_cannot_export_tenant_data(): void
    {
        $this->actingAs($this->staff)
            ->getJson('/api/tenant/export')
            ->assertForbidden();
    }
}
