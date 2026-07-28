<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantUniqueConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_email_slug_and_invoice_allowed_across_tenants(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'toko-a']);
        $tenantB = Tenant::factory()->create(['slug' => 'toko-b']);

        User::factory()->create([
            'tenant_id' => $tenantA->id,
            'email' => 'owner@toko.com',
        ]);

        User::factory()->create([
            'tenant_id' => $tenantB->id,
            'email' => 'owner@toko.com',
        ]);

        $categoryA = Category::factory()->create(['tenant_id' => $tenantA->id, 'slug' => 'sembako']);
        $categoryB = Category::factory()->create(['tenant_id' => $tenantB->id, 'slug' => 'sembako']);

        Product::factory()->create([
            'tenant_id' => $tenantA->id,
            'category_id' => $categoryA->id,
            'slug' => 'beras-5kg',
            'sku' => 'BR-5',
        ]);

        Product::factory()->create([
            'tenant_id' => $tenantB->id,
            'category_id' => $categoryB->id,
            'slug' => 'beras-5kg',
            'sku' => 'BR-5',
        ]);

        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

        Order::factory()->create([
            'tenant_id' => $tenantA->id,
            'user_id' => $userA->id,
            'invoice_no' => 'INV-TEST-001',
        ]);

        Order::factory()->create([
            'tenant_id' => $tenantB->id,
            'user_id' => $userB->id,
            'invoice_no' => 'INV-TEST-001',
        ]);

        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseHas('products', ['tenant_id' => $tenantA->id, 'slug' => 'beras-5kg']);
        $this->assertDatabaseHas('products', ['tenant_id' => $tenantB->id, 'slug' => 'beras-5kg']);
    }
}
