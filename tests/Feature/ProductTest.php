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
        $product = Product::factory()->create();

        $this->withToken($this->ownerToken)->getJson('/api/products')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product->id);
    }

    public function test_staff_can_view_products(): void
    {
        Product::factory()->create();

        $this->withToken($this->staffToken)->getJson('/api/products')->assertOk()
            ->assertJsonStructure(['data' => [['id', 'nama', 'category', 'units']], 'current_page', 'per_page', 'total', 'last_page']);
    }

    public function test_products_index_is_paginated(): void
    {
        Product::factory()->count(25)->create();

        $this->withToken($this->ownerToken)->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 20)
            ->assertJsonPath('total', 25)
            ->assertJsonPath('last_page', 2);

        $this->withToken($this->ownerToken)->getJson('/api/products?page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('current_page', 2);

        $this->withToken($this->ownerToken)->getJson('/api/products?per_page=5&page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('per_page', 5);

        $this->withToken($this->ownerToken)->getJson('/api/products?per_page=101')
            ->assertStatus(422);
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

    public function test_update_units_without_id_preserves_existing_stock(): void
    {
        $product = Product::factory()->create();
        $unit = $product->units()->first();
        $unit->update(['stok' => 77]);

        $this->withToken($this->ownerToken)->putJson('/api/products/'.$product->id, [
            'nama' => $product->nama,
            'units' => [
                [
                    'satuan' => 'pcs',
                    'harga_jual' => 9000,
                    'harga_beli' => 7000,
                    'stok' => 0,
                    'is_default' => true,
                ],
                [
                    'satuan' => 'box',
                    'harga_jual' => 50000,
                    'harga_beli' => 40000,
                    'stok' => 5,
                    'is_default' => false,
                ],
            ],
        ])->assertOk();

        $unit = $unit->fresh();
        $this->assertEquals(77, $unit->stok);
        $this->assertEquals(9000, (float) $unit->harga_jual);
        $this->assertEquals(7000, (float) $unit->harga_beli);

        $newUnit = $product->units()->where('satuan', 'box')->first();
        $this->assertNotNull($newUnit);
        $this->assertEquals(5, $newUnit->stok);
        $this->assertEquals(50000, (float) $newUnit->harga_jual);
    }

    public function test_owner_can_create_product_without_category(): void
    {
        $response = $this->withToken($this->ownerToken)->postJson('/api/products', [
            'nama' => 'Produk Lepas',
            'units' => [[
                'satuan' => 'pcs',
                'harga_jual' => 10000,
                'harga_beli' => 5000,
                'stok' => 10,
                'is_default' => true,
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('category_id', null)
            ->assertJsonPath('category', null);
    }

    public function test_owner_can_unassign_product_category(): void
    {
        $product = Product::factory()->create();

        $this->withToken($this->ownerToken)->putJson('/api/products/' . $product->id, [
            'category_id' => null,
        ])->assertOk()
            ->assertJsonPath('category_id', null)
            ->assertJsonPath('category', null);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => null]);
    }

    public function test_owner_can_move_product_to_another_category(): void
    {
        $product = Product::factory()->create();
        $target = Category::factory()->create();

        $this->withToken($this->ownerToken)->putJson('/api/products/' . $product->id, [
            'category_id' => $target->id,
        ])->assertOk()
            ->assertJsonPath('category_id', $target->id)
            ->assertJsonPath('category.nama', $target->nama);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => $target->id]);
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
