<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTenantSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_uses_single_tenant_for_owner(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'default']);

        $owner = User::factory()->owner()->create([
            'tenant_id' => $tenant->id,
            'email' => 'owner@localhost',
        ]);

        $this->postJson('/api/login', [
            'email' => 'owner@localhost',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('tenant.slug', 'default')
            ->assertJsonPath('user.id', $owner->id);
    }

    public function test_public_tenant_branding_endpoint(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'demo-shop',
            'name' => 'Demo Shop',
            'tagline' => 'Fresh goods',
        ]);

        $this->getJson('/api/public/tenant')
            ->assertOk()
            ->assertJson([
                'slug' => 'demo-shop',
                'name' => 'Demo Shop',
                'tagline' => 'Fresh goods',
            ]);

        $tenant->update(['is_suspended' => true]);

        $this->getJson('/api/public/tenant')->assertOk();
    }
}
