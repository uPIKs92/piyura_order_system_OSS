<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Database\Factories\SupplierFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantForeignKeyValidationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $ownerA;

    private string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['slug' => 'toko-a']);
        $this->tenantB = Tenant::factory()->create(['slug' => 'toko-b']);

        $this->ownerA = User::factory()->owner()->create(['tenant_id' => $this->tenantA->id]);
        $this->tokenA = $this->ownerA->createToken('test')->plainTextToken;
    }

    public function test_product_store_rejects_cross_tenant_category_id(): void
    {
        $categoryB = Category::factory()->create(['tenant_id' => $this->tenantB->id]);

        $this->withToken($this->tokenA)
            ->postJson('/api/products', [
                'category_id' => $categoryB->id,
                'nama' => 'Kopi Lain',
                'units' => [[
                    'satuan' => 'pcs',
                    'harga_jual' => 10000,
                    'harga_beli' => 8000,
                    'stok' => 5,
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('category_id');
    }

    public function test_product_store_accepts_own_tenant_category_id(): void
    {
        $categoryA = Category::factory()->create(['tenant_id' => $this->tenantA->id]);

        $this->withToken($this->tokenA)
            ->postJson('/api/products', [
                'category_id' => $categoryA->id,
                'nama' => 'Kopi Sendiri',
                'units' => [[
                    'satuan' => 'pcs',
                    'harga_jual' => 10000,
                    'harga_beli' => 8000,
                    'stok' => 5,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('category_id', $categoryA->id);
    }

    public function test_product_update_rejects_cross_tenant_category_id(): void
    {
        $productA = Product::factory()->create(['tenant_id' => $this->tenantA->id]);
        $categoryB = Category::factory()->create(['tenant_id' => $this->tenantB->id]);

        $this->withToken($this->tokenA)
            ->putJson('/api/products/'.$productA->id, [
                'category_id' => $categoryB->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('category_id');
    }

    public function test_product_update_accepts_own_tenant_category_id(): void
    {
        $productA = Product::factory()->create(['tenant_id' => $this->tenantA->id]);
        $categoryA = Category::factory()->create(['tenant_id' => $this->tenantA->id]);

        $this->withToken($this->tokenA)
            ->putJson('/api/products/'.$productA->id, [
                'category_id' => $categoryA->id,
            ])
            ->assertOk()
            ->assertJsonPath('category_id', $categoryA->id);
    }

    public function test_inventory_receipt_rejects_cross_tenant_supplier_id(): void
    {
        $supplierB = SupplierFactory::new()->create(['tenant_id' => $this->tenantB->id]);
        $productA = Product::factory()->create(['tenant_id' => $this->tenantA->id]);
        $unitA = $productA->units()->first();

        $this->withToken($this->tokenA)
            ->postJson('/api/inventory/receipts', [
                'supplier_id' => $supplierB->id,
                'items' => [
                    ['product_unit_id' => $unitA->id, 'quantity' => 10],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('supplier_id');
    }

    public function test_inventory_receipt_accepts_own_tenant_supplier_id(): void
    {
        $supplierA = SupplierFactory::new()->create(['tenant_id' => $this->tenantA->id]);
        $productA = Product::factory()->create(['tenant_id' => $this->tenantA->id]);
        $unitA = $productA->units()->first();

        $this->withToken($this->tokenA)
            ->postJson('/api/inventory/receipts', [
                'supplier_id' => $supplierA->id,
                'items' => [
                    ['product_unit_id' => $unitA->id, 'quantity' => 10],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('supplier_id', $supplierA->id);
    }
}
