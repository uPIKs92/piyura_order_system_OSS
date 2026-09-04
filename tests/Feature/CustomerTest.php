<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Database\Factories\CustomerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerTest extends TestCase
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

    public function test_backfill_imports_one_customer_per_name_with_latest_non_empty_details(): void
    {
        $earliest = Order::factory()->create([
            'invoice_no' => 'INV-BF-001',
            'customer_name' => 'Budi Santoso',
            'customer_phone' => null,
            'customer_address' => 'Jl. Kenanga No. 7',
            'order_date' => '2026-01-05',
        ]);

        Order::factory()->create([
            'invoice_no' => 'INV-BF-002',
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'customer_address' => null,
            'order_date' => '2026-02-10',
        ]);

        Order::factory()->create([
            'invoice_no' => 'INV-BF-003',
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '',
            'customer_address' => '',
            'order_date' => '2026-03-15',
        ]);

        Order::factory()->create([
            'invoice_no' => 'INV-BF-004',
            'customer_name' => 'Siti Aminah',
            'customer_phone' => '089876543210',
            'order_date' => '2026-01-20',
        ])->delete();

        Order::factory()->create([
            'invoice_no' => 'INV-BF-005',
            'customer_name' => null,
            'customer_phone' => '087777777777',
        ]);

        Order::factory()->create([
            'invoice_no' => 'INV-BF-006',
            'customer_name' => '',
        ]);

        $migration = require database_path('migrations/2026_08_30_000001_create_customers_table.php');
        $migration::backfill();

        $this->assertSame(1, DB::table('customers')->count());

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $earliest->tenant_id,
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'address' => 'Jl. Kenanga No. 7',
            'notes' => null,
        ]);
    }

    public function test_upsert_from_order_creates_customer(): void
    {
        $order = Order::factory()->create([
            'invoice_no' => 'INV-UP-001',
            'customer_name' => '  Rina Melati  ',
            'customer_phone' => '081322211100',
            'customer_address' => 'Jl. Mawar No. 12',
            'order_date' => '2026-05-10',
        ]);

        $customer = Customer::upsertFromOrder($order);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertSame($order->tenant_id, $customer->tenant_id);
        $this->assertSame('Rina Melati', $customer->name);
        $this->assertSame('081322211100', $customer->phone);
        $this->assertSame('Jl. Mawar No. 12', $customer->address);
        $this->assertSame(0, $customer->fresh()->orders_count);
        $this->assertSame('2026-05-10', $customer->last_ordered_at->toDateString());

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $order->tenant_id,
            'name' => 'Rina Melati',
            'phone' => '081322211100',
        ]);
    }

    public function test_upsert_from_order_phone_match_renames_and_keeps_blank_address(): void
    {
        $original = Customer::upsertFromOrder(Order::factory()->create([
            'invoice_no' => 'INV-UP-002',
            'customer_name' => 'Budi',
            'customer_phone' => '0811111111',
            'customer_address' => 'Jl. Lama No. 1',
            'order_date' => '2026-03-01',
        ]));
        $original->update(['notes' => 'VIP']);

        $updated = Customer::upsertFromOrder(Order::factory()->create([
            'invoice_no' => 'INV-UP-003',
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '0811111111',
            'customer_address' => null,
            'order_date' => '2026-04-01',
        ]));

        $this->assertSame($original->id, $updated->id);
        $this->assertSame('Budi Santoso', $updated->name);
        $this->assertSame('0811111111', $updated->phone);
        $this->assertSame('Jl. Lama No. 1', $updated->address);
        $this->assertSame('VIP', $updated->notes);
        $this->assertSame('2026-04-01', $updated->last_ordered_at->toDateString());

        $older = Customer::upsertFromOrder(Order::factory()->create([
            'invoice_no' => 'INV-UP-003B',
            'customer_name' => 'Budi S.',
            'customer_phone' => '0811111111',
            'customer_address' => 'Jl. Lama No. 1',
            'order_date' => '2026-02-01',
        ]));

        $this->assertSame($original->id, $older->id);
        $this->assertSame('Budi S.', $older->name);
        $this->assertSame('2026-04-01', $older->last_ordered_at->toDateString());

        $this->assertSame(1, DB::table('customers')->count());
    }

    public function test_upsert_from_order_ignores_orders_without_phone(): void
    {
        $nullPhone = Order::factory()->create([
            'invoice_no' => 'INV-UP-004',
            'customer_name' => 'Rina Melati',
            'customer_phone' => null,
        ]);

        $blankPhone = Order::factory()->create([
            'invoice_no' => 'INV-UP-005',
            'customer_name' => 'Rina Melati',
            'customer_phone' => '   ',
        ]);

        $this->assertNull(Customer::upsertFromOrder($nullPhone));
        $this->assertNull(Customer::upsertFromOrder($blankPhone));
        $this->assertSame(0, DB::table('customers')->count());
    }

    public function test_upsert_from_order_same_name_with_different_phone_creates_new_customer(): void
    {
        $first = Customer::upsertFromOrder(Order::factory()->create([
            'invoice_no' => 'INV-UP-006',
            'customer_name' => 'Budi',
            'customer_phone' => '0811111111',
        ]));

        $second = Customer::upsertFromOrder(Order::factory()->create([
            'invoice_no' => 'INV-UP-007',
            'customer_name' => 'budi',
            'customer_phone' => '0822222222',
        ]));

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('budi', $second->name);
        $this->assertSame(2, DB::table('customers')->count());
    }

    public function test_upsert_from_order_claims_same_name_row_with_empty_phone(): void
    {
        $existing = CustomerFactory::new()->create([
            'name' => 'Sari Dewi',
            'phone' => null,
            'address' => null,
        ]);

        $claimed = Customer::upsertFromOrder(Order::factory()->create([
            'invoice_no' => 'INV-UP-008',
            'customer_name' => 'sari dewi',
            'customer_phone' => '0844444444',
            'customer_address' => 'Jl. Baru No. 9',
            'order_date' => '2026-06-15',
        ]));

        $this->assertSame($existing->id, $claimed->id);
        $this->assertSame('Sari Dewi', $claimed->name);
        $this->assertSame('0844444444', $claimed->phone);
        $this->assertSame('Jl. Baru No. 9', $claimed->address);
        $this->assertSame('2026-06-15', $claimed->last_ordered_at->toDateString());
        $this->assertSame(1, DB::table('customers')->count());
    }

    public function test_upsert_from_order_with_empty_name_only_updates_phone_matched_customer(): void
    {
        $existing = Customer::upsertFromOrder(Order::factory()->create([
            'invoice_no' => 'INV-UP-009',
            'customer_name' => 'Budi',
            'customer_phone' => '0811111111',
            'order_date' => '2026-03-01',
        ]));

        $updated = Customer::upsertFromOrder(Order::factory()->create([
            'invoice_no' => 'INV-UP-010',
            'customer_name' => null,
            'customer_phone' => '0811111111',
            'order_date' => '2026-04-01',
        ]));

        $this->assertSame($existing->id, $updated->id);
        $this->assertSame('Budi', $updated->name);
        $this->assertSame('2026-04-01', $updated->last_ordered_at->toDateString());

        $stray = Order::factory()->create([
            'invoice_no' => 'INV-UP-011',
            'customer_name' => null,
            'customer_phone' => '0833333333',
        ]);

        $this->assertNull(Customer::upsertFromOrder($stray));
        $this->assertSame(1, DB::table('customers')->count());
    }

    public function test_upsert_from_order_recreates_soft_deleted_customer(): void
    {
        $order = Order::factory()->create([
            'invoice_no' => 'INV-UP-006',
            'customer_name' => 'Dewi Lestari',
            'customer_phone' => '0833333333',
        ]);

        $original = Customer::upsertFromOrder($order);
        $original->delete();

        $recreated = Customer::upsertFromOrder($order);

        $this->assertNotSame($original->id, $recreated->id);
        $this->assertNull($recreated->deleted_at);
        $this->assertSame('Dewi Lestari', $recreated->name);
        $this->assertSame(2, DB::table('customers')->count());
        $this->assertSoftDeleted('customers', ['id' => $original->id]);
    }

    public function test_owner_crud_happy_path(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/customers', [
                'name' => 'Budi Santoso',
                'phone' => '081234567890',
                'address' => 'Jl. Kenanga No. 7',
                'notes' => 'Pelanggan tetap',
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Budi Santoso')
            ->assertJsonPath('phone', '081234567890');

        $id = $response->json('id');

        $this->assertDatabaseHas('customers', [
            'id' => $id,
            'tenant_id' => $this->owner->tenant_id,
            'name' => 'Budi Santoso',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/customers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);

        $this->withToken($this->token)
            ->getJson("/api/customers/{$id}")
            ->assertOk()
            ->assertJsonPath('name', 'Budi Santoso');

        $this->withToken($this->token)
            ->putJson("/api/customers/{$id}", [
                'name' => 'Budi Santoso Updated',
                'phone' => '089876543210',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Budi Santoso Updated')
            ->assertJsonPath('phone', '089876543210');

        $this->assertDatabaseHas('customers', [
            'id' => $id,
            'name' => 'Budi Santoso Updated',
        ]);

        $this->withToken($this->token)
            ->deleteJson("/api/customers/{$id}")
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertSoftDeleted('customers', ['id' => $id]);
        $this->assertNotNull(Customer::withTrashed()->find($id));

        $this->withToken($this->token)
            ->getJson('/api/customers')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_owner_can_set_and_clear_customer_pin_location(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/customers', [
                'name' => 'Siti Aminah',
                'address' => 'Jl. Merdeka No. 10, Bandung',
                'latitude' => -6.914744,
                'longitude' => 107.609811,
            ])
            ->assertCreated()
            ->assertJsonPath('latitude', -6.914744)
            ->assertJsonPath('longitude', 107.609811);

        $id = $response->json('id');

        $this->assertDatabaseHas('customers', [
            'id' => $id,
            'latitude' => -6.914744,
            'longitude' => 107.609811,
        ]);

        $this->withToken($this->token)
            ->putJson("/api/customers/{$id}", [
                'name' => 'Siti Aminah',
                'address' => 'Jl. Merdeka No. 10, Bandung',
                'latitude' => null,
                'longitude' => null,
            ])
            ->assertOk()
            ->assertJsonPath('latitude', null)
            ->assertJsonPath('longitude', null)
            ->assertJsonPath('address', 'Jl. Merdeka No. 10, Bandung');

        $this->assertDatabaseHas('customers', [
            'id' => $id,
            'latitude' => null,
            'longitude' => null,
        ]);
    }

    public function test_pin_location_validation_rejects_out_of_range(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/customers', ['name' => 'Valid', 'latitude' => 91])
            ->assertStatus(422);

        $this->withToken($this->token)
            ->postJson('/api/customers', ['name' => 'Valid', 'longitude' => 181])
            ->assertStatus(422);

        $customer = CustomerFactory::new()->create();

        $this->withToken($this->token)
            ->putJson("/api/customers/{$customer->id}", ['latitude' => -91])
            ->assertStatus(422);

        $this->withToken($this->token)
            ->putJson("/api/customers/{$customer->id}", ['longitude' => -181])
            ->assertStatus(422);
    }

    public function test_validation_requires_name_within_limits(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/customers', ['phone' => '0812'])
            ->assertStatus(422);

        $this->withToken($this->token)
            ->postJson('/api/customers', ['name' => str_repeat('x', 256)])
            ->assertStatus(422);

        $this->withToken($this->token)
            ->postJson('/api/customers', ['name' => 'Valid', 'phone' => str_repeat('x', 31)])
            ->assertStatus(422);

        $customer = CustomerFactory::new()->create();

        $this->withToken($this->token)
            ->putJson("/api/customers/{$customer->id}", ['name' => str_repeat('x', 256)])
            ->assertStatus(422);
    }

    public function test_staff_can_read_but_not_write(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;
        $customer = CustomerFactory::new()->create();

        $this->withToken($token)->getJson('/api/customers')->assertOk();
        $this->withToken($token)->getJson("/api/customers/{$customer->id}")->assertOk();
        $this->withToken($token)->postJson('/api/customers', ['name' => 'X'])->assertForbidden();
        $this->withToken($token)->putJson("/api/customers/{$customer->id}", ['name' => 'X'])->assertForbidden();
        $this->withToken($token)->deleteJson("/api/customers/{$customer->id}")->assertForbidden();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'deleted_at' => null,
        ]);
    }

    public function test_customers_index_is_paginated(): void
    {
        CustomerFactory::new()->count(25)->create();

        $this->withToken($this->token)
            ->getJson('/api/customers')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 20)
            ->assertJsonPath('total', 25)
            ->assertJsonPath('last_page', 2);

        $this->withToken($this->token)
            ->getJson('/api/customers?page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('current_page', 2);

        $this->withToken($this->token)
            ->getJson('/api/customers?per_page=500')
            ->assertStatus(422);
    }

    public function test_index_search_filters_by_name_and_phone(): void
    {
        CustomerFactory::new()->create([
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'address' => 'Jl. Kenanga No. 7',
        ]);
        CustomerFactory::new()->create([
            'name' => 'Siti Aminah',
            'phone' => '089876543210',
            'address' => 'Jl. Melati No. 3',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/customers?search=budi')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Budi Santoso');

        $this->withToken($this->token)
            ->getJson('/api/customers?search=0898765432')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Siti Aminah');

        $this->withToken($this->token)
            ->getJson('/api/customers?search=Kenanga')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_default_index_orders_by_last_ordered_at_desc_then_name(): void
    {
        CustomerFactory::new()->create([
            'name' => 'Charlie',
            'last_ordered_at' => '2026-06-01',
        ]);
        CustomerFactory::new()->create([
            'name' => 'Zulu',
            'last_ordered_at' => null,
        ]);
        CustomerFactory::new()->create([
            'name' => 'Bravo',
            'last_ordered_at' => '2026-08-01',
        ]);
        CustomerFactory::new()->create([
            'name' => 'Alpha',
            'last_ordered_at' => '2026-08-01',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/customers')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Alpha')
            ->assertJsonPath('data.1.name', 'Bravo')
            ->assertJsonPath('data.2.name', 'Charlie')
            ->assertJsonPath('data.3.name', 'Zulu');
    }

    public function test_index_search_ranks_prefix_matches_before_contains_matches(): void
    {
        CustomerFactory::new()->create([
            'name' => 'Haji Budiarto',
            'last_ordered_at' => '2026-07-10',
        ]);
        CustomerFactory::new()->create([
            'name' => 'Budi Santoso',
            'last_ordered_at' => '2026-08-20',
        ]);
        CustomerFactory::new()->create([
            'name' => 'Budi',
            'last_ordered_at' => '2026-06-01',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/customers?search=budi')
            ->assertOk()
            ->assertJsonPath('per_page', 20)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'Budi Santoso')
            ->assertJsonPath('data.1.name', 'Budi')
            ->assertJsonPath('data.2.name', 'Haji Budiarto');

        $this->withToken($this->token)
            ->getJson('/api/customers?search=budi&per_page=1')
            ->assertOk()
            ->assertJsonPath('per_page', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Budi Santoso');
    }

}
