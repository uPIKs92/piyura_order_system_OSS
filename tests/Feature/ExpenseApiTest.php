<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseApiTest extends TestCase
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

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'expense_date' => now()->toDateString(),
            'amount' => 15000,
            'category' => 'bahan_baku',
            'note' => 'Belanja telur',
        ], $overrides);
    }

    public function test_owner_crud_happy_path(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/expenses', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('amount', '15000.00')
            ->assertJsonPath('category', 'bahan_baku')
            ->assertJsonPath('note', 'Belanja telur')
            ->assertJsonPath('user_id', $this->owner->id);

        $id = $response->json('id');

        $this->assertDatabaseHas('expenses', [
            'id' => $id,
            'tenant_id' => $this->owner->tenant_id,
            'amount' => 15000,
            'category' => 'bahan_baku',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/expenses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);

        $this->withToken($this->token)
            ->putJson("/api/expenses/{$id}", [
                'amount' => 20000,
                'category' => 'kemasan',
                'note' => 'Beli cup 50pcs',
            ])
            ->assertOk()
            ->assertJsonPath('amount', '20000.00')
            ->assertJsonPath('category', 'kemasan')
            ->assertJsonPath('note', 'Beli cup 50pcs');

        $this->withToken($this->token)
            ->deleteJson("/api/expenses/{$id}")
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseMissing('expenses', ['id' => $id]);

        $this->withToken($this->token)
            ->getJson('/api/expenses')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_index_defaults_to_today_and_orders_newest_first(): void
    {
        $yesterday = Expense::factory()->create([
            'expense_date' => now()->subDay()->toDateString(),
            'amount' => 50000,
        ]);
        $olderToday = Expense::factory()->create(['amount' => 10000]);
        $newestToday = Expense::factory()->create(['amount' => 20000]);

        $this->withToken($this->token)
            ->getJson('/api/expenses')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newestToday->id)
            ->assertJsonPath('data.1.id', $olderToday->id);

        $this->withToken($this->token)
            ->getJson('/api/expenses?from='.now()->subDay()->toDateString().'&to='.now()->subDay()->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $yesterday->id);

        $this->withToken($this->token)
            ->getJson('/api/expenses?from='.now()->subDay()->toDateString())
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_validation_rejects_invalid_amounts_dates_and_categories(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/expenses', $this->validPayload(['amount' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->withToken($this->token)
            ->postJson('/api/expenses', $this->validPayload(['amount' => -5000]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->withToken($this->token)
            ->postJson('/api/expenses', $this->validPayload(['expense_date' => now()->addDay()->toDateString()]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('expense_date');

        $this->withToken($this->token)
            ->postJson('/api/expenses', $this->validPayload(['category' => 'makan_minang']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('category');

        $this->withToken($this->token)
            ->postJson('/api/expenses', $this->validPayload(['note' => str_repeat('x', 256)]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('note');

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_update_validation_rejects_bad_fields(): void
    {
        $expense = Expense::factory()->create();

        $this->withToken($this->token)
            ->putJson("/api/expenses/{$expense->id}", ['amount' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->withToken($this->token)
            ->putJson("/api/expenses/{$expense->id}", ['category' => ' Ngaco'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('category');

        $this->withToken($this->token)
            ->putJson("/api/expenses/{$expense->id}", ['expense_date' => now()->addDay()->toDateString()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('expense_date');
    }

    public function test_staff_gets_403_on_all_expense_endpoints(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;
        $expense = Expense::factory()->create();

        $this->withToken($token)->getJson('/api/expenses')->assertForbidden();
        $this->withToken($token)->postJson('/api/expenses', $this->validPayload())->assertForbidden();
        $this->withToken($token)->putJson("/api/expenses/{$expense->id}", ['amount' => 999])->assertForbidden();
        $this->withToken($token)->deleteJson("/api/expenses/{$expense->id}")->assertForbidden();

        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
    }

    public function test_index_is_paginated(): void
    {
        Expense::factory()->count(25)->create();

        $this->withToken($this->token)
            ->getJson('/api/expenses')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 20)
            ->assertJsonPath('total', 25)
            ->assertJsonPath('last_page', 2);

        $this->withToken($this->token)
            ->getJson('/api/expenses?page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('current_page', 2);

        $this->withToken($this->token)
            ->getJson('/api/expenses?per_page=500')
            ->assertStatus(422);
    }

    public function test_mutations_bump_aggregates_version(): void
    {
        $tenantId = $this->owner->tenant_id;
        $before = OrderService::aggregatesVersion($tenantId);

        $response = $this->withToken($this->token)
            ->postJson('/api/expenses', $this->validPayload())
            ->assertCreated();
        $id = $response->json('id');

        $this->assertGreaterThan($before, OrderService::aggregatesVersion($tenantId));

        $before = OrderService::aggregatesVersion($tenantId);
        $this->withToken($this->token)
            ->putJson("/api/expenses/{$id}", ['amount' => 30000])
            ->assertOk();
        $this->assertGreaterThan($before, OrderService::aggregatesVersion($tenantId));

        $before = OrderService::aggregatesVersion($tenantId);
        $this->withToken($this->token)
            ->deleteJson("/api/expenses/{$id}")
            ->assertOk();
        $this->assertGreaterThan($before, OrderService::aggregatesVersion($tenantId));
    }
}
