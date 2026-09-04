<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OrderService;
use App\Support\TenantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private string $ownerToken;
    private User $staff;
    private string $staffToken;
    private Product $product;
    private ProductUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        $this->ownerToken = $this->owner->createToken('test')->plainTextToken;
        $this->staff = User::factory()->create();
        $this->staffToken = $this->staff->createToken('test')->plainTextToken;
        $this->product = Product::factory()->create();
        $this->unit = $this->product->units()->first();
        $this->unit->update(['stok' => 50, 'harga_jual' => 10000]);
    }

    public function test_staff_can_create_draft_order(): void
    {
        $this->unit->update(['harga_beli' => 4500]);

        $response = $this->withToken($this->staffToken)->postJson('/api/orders', [
            'customer_name' => 'Budi',
            'customer_phone' => '08123456789',
            'order_date' => now()->toDateString(),
            'items' => [['product_unit_id' => $this->unit->id, 'quantity' => 2]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('user_id', $this->staff->id);

        $this->assertDatabaseHas('order_items', [
            'product_name' => $this->product->nama,
            'satuan' => $this->unit->satuan,
            'cost_snapshot' => 4500,
        ]);
    }

    public function test_orders_list_includes_item_summaries(): void
    {
        $secondUnit = ProductUnit::factory()->for($this->product)->create([
            'satuan' => 'dus',
            'is_default' => false,
            'stok' => 50,
            'harga_jual' => 100000,
            'harga_beli' => 90000,
        ]);

        $this->withToken($this->staffToken)->postJson('/api/orders', [
            'customer_name' => 'Budi',
            'order_date' => now()->toDateString(),
            'items' => [
                ['product_unit_id' => $this->unit->id, 'quantity' => 2],
                ['product_unit_id' => $secondUnit->id, 'quantity' => 3],
            ],
        ])->assertCreated();

        $response = $this->withToken($this->ownerToken)->getJson('/api/orders')
            ->assertOk();

        $items = $response->json('data.0.items');
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
        $this->assertCount(2, $items);

        $response->assertJsonPath('data.0.items.0.product_name', $this->product->nama)
            ->assertJsonPath('data.0.items.0.satuan', $this->unit->satuan)
            ->assertJsonPath('data.0.items.0.quantity', 2)
            ->assertJsonPath('data.0.items.1.satuan', 'dus')
            ->assertJsonPath('data.0.items.1.quantity', 3)
            ->assertJsonMissingPath('data.0.items_count');
    }

    public function test_orders_can_be_filtered_by_month_and_year(): void
    {
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'order_date' => '2026-07-10',
            'invoice_no' => 'INV-JUL-001',
        ]);
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'order_date' => '2026-08-15',
            'invoice_no' => 'INV-AUG-001',
        ]);
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'order_date' => '2025-07-20',
            'invoice_no' => 'INV-OLD-001',
        ]);

        $this->withToken($this->ownerToken)
            ->getJson('/api/orders?month=7&year=2026')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invoice_no', 'INV-JUL-001');

        $this->withToken($this->ownerToken)
            ->getJson('/api/orders?year=2026')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withToken($this->ownerToken)
            ->getJson('/api/orders?month=7')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_orders_can_be_sorted(): void
    {
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'order_date' => '2026-07-10',
            'invoice_no' => 'INV-A',
            'grand_total' => 50000,
        ]);
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'order_date' => '2026-07-20',
            'invoice_no' => 'INV-C',
            'grand_total' => 100000,
        ]);
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'order_date' => '2026-07-15',
            'invoice_no' => 'INV-B',
            'grand_total' => 75000,
        ]);

        $this->withToken($this->ownerToken)
            ->getJson('/api/orders?sort=date_desc')
            ->assertOk()
            ->assertJsonPath('data.0.invoice_no', 'INV-C')
            ->assertJsonPath('data.2.invoice_no', 'INV-A');

        $this->withToken($this->ownerToken)
            ->getJson('/api/orders?sort=date_asc')
            ->assertOk()
            ->assertJsonPath('data.0.invoice_no', 'INV-A')
            ->assertJsonPath('data.2.invoice_no', 'INV-C');

        $this->withToken($this->ownerToken)
            ->getJson('/api/orders?sort=amount_desc')
            ->assertOk()
            ->assertJsonPath('data.0.invoice_no', 'INV-C')
            ->assertJsonPath('data.2.invoice_no', 'INV-A');

        $this->withToken($this->ownerToken)
            ->getJson('/api/orders?sort=invoice_asc')
            ->assertOk()
            ->assertJsonPath('data.0.invoice_no', 'INV-A')
            ->assertJsonPath('data.2.invoice_no', 'INV-C');
    }

    public function test_orders_can_be_paginated(): void
    {
        Order::factory()->count(25)->create(['user_id' => $this->staff->id]);

        $this->withToken($this->ownerToken)
            ->getJson('/api/orders?per_page=10&page=1')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('total', 25)
            ->assertJsonPath('last_page', 3)
            ->assertJsonPath('current_page', 1);

        $this->withToken($this->ownerToken)
            ->getJson('/api/orders?per_page=10&page=3')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('current_page', 3);
    }

    public function test_staff_only_sees_own_orders(): void
    {
        Order::factory()->create(['user_id' => $this->staff->id]);
        Order::factory()->create(['user_id' => $this->owner->id]);

        $this->withToken($this->staffToken)->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_owner_sees_all_orders(): void
    {
        Order::factory()->count(2)->create(['user_id' => $this->staff->id]);
        Order::factory()->create(['user_id' => $this->owner->id]);

        $this->withToken($this->ownerToken)->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_list_includes_minimal_item_summaries(): void
    {
        $order = Order::factory()->create(['user_id' => $this->staff->id]);
        $order->items()->create([
            'product_id' => $this->product->id,
            'product_unit_id' => $this->unit->id,
            'product_name' => $this->product->nama,
            'satuan' => $this->unit->satuan,
            'price_snapshot' => 10000,
            'cost_snapshot' => 0,
            'quantity' => 2,
            'subtotal' => 20000,
        ]);

        $response = $this->withToken($this->staffToken)->getJson('/api/orders')
            ->assertOk();

        $row = $response->json('data.0');

        // List payload stays lean: no full relations are eager-loaded.
        $this->assertArrayNotHasKey('user', $row);

        // Items are included as minimal summaries only (no price/subtotal snapshots).
        $this->assertSame(
            ['id', 'order_id', 'product_name', 'satuan', 'quantity'],
            array_keys($row['items'][0]),
        );

        // The separate withCount column is gone — the count derives from the
        // loaded item summaries.
        $this->assertArrayNotHasKey('items_count', $row);
        $this->assertSame(1, count($row['items']));
    }

    public function test_status_filter_rejects_unknown_status(): void
    {
        $this->withToken($this->ownerToken)
            ->getJson('/api/orders?status=menunggu')
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_status_filter_accepts_canonical_statuses(): void
    {
        foreach (['draft', 'pending', 'diproses', 'dikirim', 'selesai', 'cancelled'] as $status) {
            $this->withToken($this->ownerToken)
                ->getJson('/api/orders?status='.$status)
                ->assertOk();
        }
    }

    public function test_search_is_capped_to_100_characters(): void
    {
        // A 100-char customer name plus a 150-char search: without the cap the
        // LIKE would never match; with it, the truncated 100-char prefix does.
        Order::factory()->create([
            'tenant_id' => $this->owner->tenant_id,
            'user_id' => $this->owner->id,
            'invoice_no' => 'INV-SEARCH-001',
            'customer_name' => str_repeat('a', 100),
        ]);

        $this->withToken($this->ownerToken)
            ->getJson('/api/orders?search='.str_repeat('a', 150))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invoice_no', 'INV-SEARCH-001');
    }

    public function test_create_retries_when_invoice_number_collides(): void
    {
        // generateInvoiceNo() reads the latest-id row with today's prefix, so
        // -003 at the higher id makes it propose -004, which already exists at
        // the lower id — forcing the duplicate-key retry path.
        $today = now()->format('Ymd');
        Order::factory()->create([
            'tenant_id' => $this->owner->tenant_id,
            'user_id' => $this->owner->id,
            'invoice_no' => "INV-{$today}-004",
        ]);
        Order::factory()->create([
            'tenant_id' => $this->owner->tenant_id,
            'user_id' => $this->owner->id,
            'invoice_no' => "INV-{$today}-003",
        ]);

        $response = $this->withToken($this->ownerToken)->postJson('/api/orders', [
            'customer_name' => 'Collision',
        ])->assertCreated();

        $response->assertJsonPath('invoice_no', "INV-{$today}-005");
        $this->assertDatabaseHas('orders', ['invoice_no' => "INV-{$today}-005"]);
    }

    public function test_optimistic_locking_returns_409(): void
    {
        $order = Order::factory()->create(['user_id' => $this->staff->id, 'version' => 2]);

        $this->withToken($this->staffToken)->putJson("/api/orders/{$order->id}", [
            'version' => 1,
            'customer_name' => 'Updated',
        ])->assertStatus(409);
    }

    public function test_checkout_decrements_stock(): void
    {
        $order = Order::factory()->create(['user_id' => $this->staff->id, 'status' => OrderStatus::Draft]);
        $order->items()->create([
            'product_id' => $this->product->id,
            'product_unit_id' => $this->unit->id,
            'product_name' => $this->product->nama,
            'satuan' => $this->unit->satuan,
            'price_snapshot' => 10000,
            'quantity' => 5,
            'subtotal' => 50000,
        ]);

        $this->withToken($this->staffToken)->putJson("/api/orders/{$order->id}", [
            'version' => $order->version,
            'status' => 'pending',
        ])->assertOk();

        $this->assertEquals(45, $this->unit->fresh()->stok);
    }

    public function test_invalid_status_transition_returns_422(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->staff->id,
            'status' => OrderStatus::Dikirim,
        ]);

        $this->withToken($this->staffToken)->putJson("/api/orders/{$order->id}", [
            'version' => $order->version,
            'status' => 'pending',
        ])->assertStatus(422);
    }

    public function test_order_date_validation(): void
    {
        $this->withToken($this->staffToken)->postJson('/api/orders', [
            'order_date' => now()->addDay()->toDateString(),
        ])->assertStatus(422);
    }

    public function test_owner_can_force_delete(): void
    {
        $order = Order::factory()->create(['user_id' => $this->staff->id]);

        $this->withToken($this->ownerToken)->deleteJson("/api/orders/{$order->id}?force=1")
            ->assertOk();

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_percent_discount_applied_to_line_item(): void
    {
        $response = $this->withToken($this->staffToken)->postJson('/api/orders', [
            'customer_name' => 'Diskon',
            'order_date' => now()->toDateString(),
            'items' => [[
                'product_unit_id' => $this->unit->id,
                'quantity' => 2,
                'discount_type' => 'percent',
                'discount_value' => 10,
            ]],
        ]);

        $response->assertCreated();
        $orderId = $response->json('id');

        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'discount_type' => 'percent',
            'discount_value' => 10,
            'subtotal' => 18000,
        ]);
    }

    public function test_kirim_pesanan_updates_existing_draft_with_frontend_payload(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->staff->id,
            'status' => OrderStatus::Draft,
        ]);
        $order->items()->create([
            'product_id' => $this->product->id,
            'product_unit_id' => $this->unit->id,
            'product_name' => $this->product->nama,
            'satuan' => $this->unit->satuan,
            'price_snapshot' => 10000,
            'quantity' => 2,
            'subtotal' => 20000,
        ]);

        $this->withToken($this->staffToken)->putJson("/api/orders/{$order->id}", [
            'version' => $order->version,
            'customer_name' => 'Budi',
            'customer_phone' => '08123456789',
            'order_date' => now()->toDateString(),
            'notes' => '',
            'status' => 'pending',
            'items' => [[
                'product_unit_id' => $this->unit->id,
                'quantity' => 2,
                'discount_type' => 'fixed',
                'discount_value' => 0,
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        $this->assertEquals(48, $this->unit->fresh()->stok);
    }

    public function test_kirim_pesanan_creates_then_submits_new_order(): void
    {
        $create = $this->withToken($this->staffToken)->postJson('/api/orders', [
            'customer_name' => 'Budi',
            'customer_phone' => '08123456789',
            'order_date' => now()->toDateString(),
            'notes' => '',
            'items' => [[
                'product_unit_id' => $this->unit->id,
                'quantity' => 2,
                'discount_type' => 'fixed',
                'discount_value' => 0,
            ]],
        ])->assertCreated();

        $orderId = $create->json('id');
        $version = $create->json('version');

        $this->withToken($this->staffToken)->putJson("/api/orders/{$orderId}", [
            'version' => $version,
            'status' => 'pending',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        $this->assertEquals(48, $this->unit->fresh()->stok);
    }

    public function test_kirim_pesanan_syncs_item_changes_before_submitting(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->staff->id,
            'status' => OrderStatus::Draft,
        ]);
        $order->items()->create([
            'product_id' => $this->product->id,
            'product_unit_id' => $this->unit->id,
            'product_name' => $this->product->nama,
            'satuan' => $this->unit->satuan,
            'price_snapshot' => 10000,
            'quantity' => 2,
            'subtotal' => 20000,
        ]);

        $this->withToken($this->staffToken)->putJson("/api/orders/{$order->id}", [
            'version' => $order->version,
            'status' => 'pending',
            'items' => [[
                'product_unit_id' => $this->unit->id,
                'quantity' => 5,
            ]],
        ])->assertOk()->assertJsonPath('status', 'pending');

        $this->assertEquals(45, $this->unit->fresh()->stok);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'quantity' => 5,
        ]);
    }

    public function test_submitting_draft_with_insufficient_stock_returns_422(): void
    {
        $this->unit->update(['stok' => 0]);

        $order = Order::factory()->create([
            'user_id' => $this->staff->id,
            'status' => OrderStatus::Draft,
        ]);
        $order->items()->create([
            'product_id' => $this->product->id,
            'product_unit_id' => $this->unit->id,
            'product_name' => $this->product->nama,
            'satuan' => $this->unit->satuan,
            'price_snapshot' => 10000,
            'quantity' => 1,
            'subtotal' => 10000,
        ]);

        $this->withToken($this->staffToken)->putJson("/api/orders/{$order->id}", [
            'version' => $order->version,
            'status' => 'pending',
        ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => "Stok {$this->product->nama} tidak cukup. Tersedia: 0, dibutuhkan: 1."]);

        $this->assertSame(OrderStatus::Draft, $order->fresh()->status);
    }

    public function test_reassign_moves_orders_and_rejects_staff_and_cross_tenant_user(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create();
        $orderA = Order::factory()->create(['user_id' => $from->id]);
        $orderB = Order::factory()->create(['user_id' => $from->id]);

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/orders/reassign', [
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
        ])->assertOk()->assertJsonPath('count', 2);

        $this->assertSame($to->id, $orderA->fresh()->user_id);
        $this->assertSame($to->id, $orderB->fresh()->user_id);

        $this->actingAs($this->staff, 'sanctum')->postJson('/api/orders/reassign', [
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
        ])->assertForbidden();

        $outsider = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/orders/reassign', [
            'from_user_id' => $to->id,
            'to_user_id' => $outsider->id,
        ])->assertStatus(422);
    }

    public function test_percent_discount_above_100_is_rejected(): void
    {
        $this->withToken($this->staffToken)->postJson('/api/orders', [
            'customer_name' => 'Diskon Berlebih',
            'order_date' => now()->toDateString(),
            'items' => [[
                'product_unit_id' => $this->unit->id,
                'quantity' => 2,
                'discount_type' => 'percent',
                'discount_value' => 150,
            ]],
        ])->assertStatus(422);

        $this->assertSame(0, Order::count());
    }

    public function test_percent_discount_above_100_is_rejected_on_update(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->staff->id,
            'status' => OrderStatus::Draft,
        ]);

        $this->withToken($this->staffToken)->putJson("/api/orders/{$order->id}", [
            'version' => $order->version,
            'items' => [[
                'product_unit_id' => $this->unit->id,
                'quantity' => 2,
                'discount_type' => 'percent',
                'discount_value' => 101,
            ]],
        ])->assertStatus(422);

        $this->assertSame(1, (int) $order->fresh()->version);
    }

    public function test_percent_discount_is_capped_at_line_subtotal(): void
    {
        $order = app(OrderService::class)->create($this->staff, [
            'customer_name' => 'Diskon Cap',
            'items' => [[
                'product_unit_id' => $this->unit->id,
                'quantity' => 2,
                'discount_type' => 'percent',
                'discount_value' => 150,
            ]],
        ]);

        $item = $order->items->first();
        $this->assertSame(0.0, (float) $item->subtotal);
        $this->assertSame(20000.0, (float) $order->discount_total);
        $this->assertSame(0.0, (float) $order->grand_total);
        $this->assertGreaterThanOrEqual(0, (float) $item->subtotal);
    }

    public function test_totals_are_cent_exact_with_item_discount_and_ppn(): void
    {
        TenantSettings::for($this->staff->tenant_id)->updatePpn(true, 11.0);
        $this->unit->update(['harga_jual' => 3333.33]);

        // By hand in integer cents (half-up at each persisted value):
        // line = 333333c * 3 = 999999c (9999.99)
        // disc 10% = floor((999999*1000 + 5000)/10000) = 100000c (999.999 rounds up)
        // item subtotal = 999999 - 100000 = 899999c (8999.99)
        // after-discount = 899999c
        // PPN 11% = floor((899999*1100 + 5000)/10000) = 99000c (989.9989 rounds up)
        // grand = 899999 + 99000 = 998999c (9989.99), sum of persisted parts.
        $response = $this->withToken($this->staffToken)->postJson('/api/orders', [
            'customer_name' => 'Sen',
            'order_date' => now()->toDateString(),
            'items' => [[
                'product_unit_id' => $this->unit->id,
                'quantity' => 3,
                'discount_type' => 'percent',
                'discount_value' => 10,
            ]],
        ])->assertCreated();

        $order = Order::whereKey($response->json('id'))->first();
        $item = $order->items->first();

        $this->assertSame(3333.33, (float) $item->price_snapshot);
        $this->assertSame(8999.99, (float) $item->subtotal);
        $this->assertSame(9999.99, (float) $order->subtotal);
        $this->assertSame(1000.0, (float) $order->discount_total);
        $this->assertSame(11.0, (float) $order->ppn_percentage);
        $this->assertSame(990.0, (float) $order->ppn_amount);
        $this->assertSame(9989.99, (float) $order->grand_total);
    }

    public function test_totals_are_cent_exact_without_discount_with_ppn(): void
    {
        TenantSettings::for($this->staff->tenant_id)->updatePpn(true, 11.0);
        $this->unit->update(['harga_jual' => 1999.99]);

        // line = 199999c * 7 = 1399993c (13999.93), no discount;
        // PPN 11% = floor((1399993*1100 + 5000)/10000) = 153999c (1539.9923 -> 1539.99);
        // grand = 1399993 + 153999 = 1553992c (15539.92).
        $response = $this->withToken($this->staffToken)->postJson('/api/orders', [
            'customer_name' => 'Sen',
            'order_date' => now()->toDateString(),
            'items' => [['product_unit_id' => $this->unit->id, 'quantity' => 7]],
        ])->assertCreated();

        $order = Order::whereKey($response->json('id'))->first();
        $item = $order->items->first();

        $this->assertSame(1999.99, (float) $item->price_snapshot);
        $this->assertSame(13999.93, (float) $item->subtotal);
        $this->assertSame(13999.93, (float) $order->subtotal);
        $this->assertSame(0.0, (float) $order->discount_total);
        $this->assertSame(1539.99, (float) $order->ppn_amount);
        $this->assertSame(15539.92, (float) $order->grand_total);
    }

    public function test_creating_order_syncs_customer_directory_for_owner_and_staff(): void
    {
        $this->withToken($this->staffToken)->postJson('/api/orders', [
            'customer_name' => 'Budi',
            'customer_phone' => '08123456789',
            'customer_address' => 'Jl. Merdeka No. 1',
            'order_date' => now()->toDateString(),
            'items' => [['product_unit_id' => $this->unit->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $this->staff->tenant_id,
            'name' => 'Budi',
            'phone' => '08123456789',
            'address' => 'Jl. Merdeka No. 1',
        ]);

        $this->withToken($this->ownerToken)->postJson('/api/orders', [
            'customer_name' => 'Siti',
            'customer_phone' => '08987654321',
            'order_date' => now()->toDateString(),
            'items' => [['product_unit_id' => $this->unit->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $this->owner->tenant_id,
            'name' => 'Siti',
            'phone' => '08987654321',
        ]);
        $this->assertSame(2, Customer::count());
    }

    public function test_creating_order_without_phone_does_not_sync_customer_directory(): void
    {
        $this->withToken($this->staffToken)->postJson('/api/orders', [
            'customer_name' => 'Tanpa Telepon',
            'order_date' => now()->toDateString(),
            'items' => [['product_unit_id' => $this->unit->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->assertSame(0, Customer::count());
    }

    public function test_subsequent_order_with_same_phone_updates_existing_customer(): void
    {
        $this->withToken($this->staffToken)->postJson('/api/orders', [
            'customer_name' => 'Budi',
            'customer_phone' => '08123456789',
            'order_date' => now()->toDateString(),
            'items' => [['product_unit_id' => $this->unit->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->withToken($this->staffToken)->postJson('/api/orders', [
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '08123456789',
            'customer_address' => 'Jl. Merdeka No. 2',
            'order_date' => now()->toDateString(),
            'items' => [['product_unit_id' => $this->unit->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $this->staff->tenant_id,
            'name' => 'Budi Santoso',
            'phone' => '08123456789',
            'address' => 'Jl. Merdeka No. 2',
        ]);
        $this->assertSame(1, Customer::count());
    }

    public function test_later_order_with_same_name_and_empty_contact_fields_does_not_wipe_directory(): void
    {
        $this->withToken($this->staffToken)->postJson('/api/orders', [
            'customer_name' => 'Budi',
            'customer_phone' => '08123456789',
            'customer_address' => 'Jl. Merdeka No. 1',
            'order_date' => now()->toDateString(),
            'items' => [['product_unit_id' => $this->unit->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->withToken($this->staffToken)->postJson('/api/orders', [
            'customer_name' => 'budi',
            'order_date' => now()->toDateString(),
            'items' => [['product_unit_id' => $this->unit->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $this->staff->tenant_id,
            'name' => 'Budi',
            'phone' => '08123456789',
            'address' => 'Jl. Merdeka No. 1',
        ]);
        $this->assertSame(1, Customer::count());
    }

    public function test_updating_order_customer_fields_syncs_directory(): void
    {
        $create = $this->withToken($this->staffToken)->postJson('/api/orders', [
            'customer_name' => 'Budi',
            'customer_phone' => '08123456789',
            'order_date' => now()->toDateString(),
            'items' => [['product_unit_id' => $this->unit->id, 'quantity' => 1]],
        ])->assertCreated();

        $orderId = $create->json('id');
        $version = $create->json('version');

        $this->withToken($this->staffToken)->putJson("/api/orders/{$orderId}", [
            'version' => $version,
            'customer_phone' => '08999999999',
        ])->assertOk();

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $this->staff->tenant_id,
            'name' => 'Budi',
            'phone' => '08999999999',
        ]);

        $this->withToken($this->staffToken)->putJson("/api/orders/{$orderId}", [
            'version' => $version + 1,
            'customer_name' => 'Budi Santoso',
        ])->assertOk();

        $this->assertDatabaseHas('customers', [
            'name' => 'Budi Santoso',
            'phone' => '08999999999',
        ]);
        $this->assertDatabaseHas('customers', ['name' => 'Budi']);
        $this->assertSame(2, Customer::count());
    }

    public function test_update_without_customer_fields_does_not_touch_directory(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->staff->id,
            'status' => OrderStatus::Draft,
            'customer_name' => 'Sari',
        ]);

        $this->withToken($this->staffToken)->putJson("/api/orders/{$order->id}", [
            'version' => $order->version,
            'notes' => 'catatan baru',
        ])->assertOk();

        $this->assertSame(0, Customer::count());
    }
}
