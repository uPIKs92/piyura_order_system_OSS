<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class BelongsToTenantGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_without_tenant_id_or_authenticated_user_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('currentTenantId');

        Product::create([
            'nama' => 'Guard Test',
            'slug' => 'guard-test',
        ]);
    }

    public function test_creating_with_explicit_tenant_id_does_not_throw(): void
    {
        $tenant = Tenant::factory()->create();

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'nama' => 'Explicit Tenant',
            'slug' => 'explicit-tenant',
        ]);

        $this->assertTrue($product->exists);
        $this->assertSame($tenant->id, $product->tenant_id);
    }

    public function test_authenticated_user_fills_tenant_id_automatically(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->owner()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user);

        $product = Product::create([
            'nama' => 'Auth Fallback',
            'slug' => 'auth-fallback',
        ]);

        $this->assertSame($tenant->id, $product->tenant_id);
    }
}
