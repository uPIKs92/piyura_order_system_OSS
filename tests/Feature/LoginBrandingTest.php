<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Support\LoginTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_embeds_default_tenant_branding(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'MAMARY MART 88',
            'tagline' => 'Order Management System',
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('MAMARY MART 88', false)
            ->assertSee('Order Management System', false);
    }

    public function test_login_tenant_resolver_returns_first_tenant(): void
    {
        $first = Tenant::factory()->create([
            'slug' => 'other-shop',
            'name' => 'Other Shop',
        ]);

        Tenant::factory()->create([
            'slug' => 'mamary-mart-88',
            'name' => 'MAMARY MART 88',
        ]);

        $this->assertTrue($first->fresh()->is(LoginTenant::resolve()));
    }
}
