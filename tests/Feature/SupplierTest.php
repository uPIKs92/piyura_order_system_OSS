<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Factories\SupplierFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
        $this->token = $this->owner->createToken('test')->plainTextToken;
    }

    public function test_owner_crud_happy_path(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/suppliers', [
                'name' => 'Supplier ABC',
                'phone' => '081234567890',
                'address' => 'Jl. Merdeka No. 1',
                'notes' => 'Utama',
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Supplier ABC')
            ->assertJsonPath('phone', '081234567890');

        $id = $response->json('id');

        $this->assertDatabaseHas('suppliers', [
            'id' => $id,
            'tenant_id' => $this->owner->tenant_id,
            'name' => 'Supplier ABC',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/suppliers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);

        $this->withToken($this->token)
            ->getJson("/api/suppliers/{$id}")
            ->assertOk()
            ->assertJsonPath('name', 'Supplier ABC');

        $this->withToken($this->token)
            ->putJson("/api/suppliers/{$id}", [
                'name' => 'Supplier ABC Updated',
                'phone' => '089876543210',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Supplier ABC Updated')
            ->assertJsonPath('phone', '089876543210');

        $this->assertDatabaseHas('suppliers', [
            'id' => $id,
            'name' => 'Supplier ABC Updated',
        ]);

        $this->withToken($this->token)
            ->deleteJson("/api/suppliers/{$id}")
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertSoftDeleted('suppliers', ['id' => $id]);

        $this->withToken($this->token)
            ->getJson('/api/suppliers')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_validation_requires_name_within_limits(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/suppliers', ['phone' => '0812'])
            ->assertStatus(422);

        $this->withToken($this->token)
            ->postJson('/api/suppliers', ['name' => str_repeat('x', 151)])
            ->assertStatus(422);

        $supplier = SupplierFactory::new()->create();

        $this->withToken($this->token)
            ->putJson("/api/suppliers/{$supplier->id}", ['name' => str_repeat('x', 151)])
            ->assertStatus(422);
    }

    public function test_staff_forbidden_on_all_actions(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;
        $supplier = SupplierFactory::new()->create();

        $this->withToken($token)->getJson('/api/suppliers')->assertForbidden();
        $this->withToken($token)->postJson('/api/suppliers', ['name' => 'X'])->assertForbidden();
        $this->withToken($token)->getJson("/api/suppliers/{$supplier->id}")->assertForbidden();
        $this->withToken($token)->putJson("/api/suppliers/{$supplier->id}", ['name' => 'X'])->assertForbidden();
        $this->withToken($token)->deleteJson("/api/suppliers/{$supplier->id}")->assertForbidden();
    }

    public function test_suppliers_index_is_paginated(): void
    {
        SupplierFactory::new()->count(25)->create();

        $this->withToken($this->token)
            ->getJson('/api/suppliers')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 20)
            ->assertJsonPath('total', 25)
            ->assertJsonPath('last_page', 2);

        $this->withToken($this->token)
            ->getJson('/api/suppliers?page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('current_page', 2);

        $this->withToken($this->token)
            ->getJson('/api/suppliers?per_page=500')
            ->assertStatus(422);
    }
}
