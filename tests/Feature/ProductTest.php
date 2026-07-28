<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private string $ownerToken;
    private User $staff;
    private string $staffToken;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        $this->ownerToken = $this->owner->createToken('test')->plainTextToken;
        $this->staff = User::factory()->create();
        $this->staffToken = $this->staff->createToken('test')->plainTextToken;
        $this->category = Category::factory()->create();
    }

    public function test_owner_can_create_product_with_units(): void
    {
        $response = $this->withToken($this->ownerToken)->postJson('/api/products', [
            'category_id' => $this->category->id,
            'nama' => 'Kopi Arabika',
            'sku' => 'KOPI-001',
            'units' => [[
                'satuan' => 'pcs',
                'harga_jual' => 25000,
                'harga_beli' => 15000,
                'stok' => 100,
                'is_default' => true,
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('nama', 'Kopi Arabika')
            ->assertJsonPath('units.0.harga_jual', '25000.00')
            ->assertJsonPath('units.0.satuan', 'pcs');
    }

    public function test_owner_can_create_product_with_multiple_units(): void
    {
        $response = $this->withToken($this->ownerToken)->postJson('/api/products', [
            'category_id' => $this->category->id,
            'nama' => 'Telur Omega',
            'units' => [
                ['satuan' => 'pack', 'harga_jual' => 35000, 'harga_beli' => 30000, 'stok' => 10, 'is_default' => true],
                ['satuan' => 'krat', 'harga_jual' => 90000, 'harga_beli' => 80000, 'stok' => 5, 'is_default' => false],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonCount(2, 'units');
    }

    public function test_owner_can_view_products(): void
    {
        Product::factory()->create();

        $this->withToken($this->ownerToken)->getJson('/api/products')->assertOk();
    }

    public function test_staff_can_view_products(): void
    {
        Product::factory()->create();

        $this->withToken($this->staffToken)->getJson('/api/products')->assertOk();
    }

    public function test_staff_cannot_create_product(): void
    {
        $this->withToken($this->staffToken)->postJson('/api/products', [
            'category_id' => $this->category->id,
            'nama' => 'Test',
            'units' => [[
                'satuan' => 'pcs',
                'harga_jual' => 100,
                'harga_beli' => 50,
                'stok' => 1,
                'is_default' => true,
            ]],
        ])->assertForbidden();
    }

    public function test_owner_can_update_product_units(): void
    {
        $product = Product::factory()->create();

        $this->withToken($this->ownerToken)->putJson('/api/products/'.$product->id, [
            'nama' => 'Updated Product',
            'units' => [[
                'id' => $product->units()->first()->id,
                'satuan' => 'pcs',
                'harga_jual' => 50000,
                'harga_beli' => 40000,
                'stok' => 20,
                'is_default' => true,
            ]],
        ])->assertOk()
            ->assertJsonPath('nama', 'Updated Product')
            ->assertJsonPath('units.0.harga_jual', '50000.00');
    }

    public function test_owner_can_delete_product(): void
    {
        $product = Product::factory()->create();

        $this->withToken($this->ownerToken)->deleteJson('/api/products/'.$product->id)->assertOk();
        $this->assertSoftDeleted($product);
    }

    public function test_product_belongs_to_category(): void
    {
        $product = Product::factory()->create();

        $this->assertNotNull($product->category);
        $this->assertInstanceOf(Category::class, $product->category);
    }
}
