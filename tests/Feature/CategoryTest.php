<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private string $ownerToken;
    private User $staff;
    private string $staffToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        $this->ownerToken = $this->owner->createToken('test')->plainTextToken;
        $this->staff = User::factory()->create();
        $this->staffToken = $this->staff->createToken('test')->plainTextToken;
    }

    public function test_owner_can_create_category(): void
    {
        $response = $this->withToken($this->ownerToken)->postJson('/api/categories', [
            'nama' => 'Minuman',
            'deskripsi' => 'All beverages',
        ]);

        $response->assertCreated()
            ->assertJson(['nama' => 'Minuman', 'slug' => 'minuman']);
    }

    public function test_owner_can_view_categories(): void
    {
        Category::factory()->create(['nama' => 'Makanan']);

        $response = $this->withToken($this->ownerToken)->getJson('/api/categories');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Makanan')
            ->assertJsonStructure(['data', 'current_page', 'per_page', 'total', 'last_page']);
    }

    public function test_categories_index_is_paginated(): void
    {
        Category::factory()->count(22)->create();

        $this->withToken($this->ownerToken)->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('per_page', 20)
            ->assertJsonPath('total', 22)
            ->assertJsonPath('last_page', 2);

        $this->withToken($this->ownerToken)->getJson('/api/categories?page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withToken($this->ownerToken)->getJson('/api/categories?per_page=101')
            ->assertStatus(422);
    }

    public function test_staff_can_view_categories(): void
    {
        Category::factory()->create(['nama' => 'Makanan']);

        $this->withToken($this->staffToken)->getJson('/api/categories')->assertOk();
    }

    public function test_staff_cannot_create_category(): void
    {
        $this->withToken($this->staffToken)->postJson('/api/categories', [
            'nama' => 'Minuman',
        ])->assertForbidden();
    }

    public function test_owner_can_update_category(): void
    {
        $category = Category::factory()->create();

        $this->withToken($this->ownerToken)->putJson('/api/categories/' . $category->id, [
            'nama' => 'Updated',
        ])->assertOk()
            ->assertJson(['nama' => 'Updated']);
    }

    public function test_owner_can_delete_category(): void
    {
        $category = Category::factory()->create();

        $this->withToken($this->ownerToken)->deleteJson('/api/categories/' . $category->id)->assertOk();
        $this->assertSoftDeleted($category);
    }

    public function test_deleting_category_detaches_products_instead_of_deleting_them(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->withToken($this->ownerToken)->deleteJson('/api/categories/' . $category->id)->assertOk();

        $this->assertSoftDeleted($category);
        $this->assertNotSoftDeleted($product);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => null]);
    }
}
