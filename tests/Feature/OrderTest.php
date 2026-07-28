<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
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
        $response = $this->withToken($this->staffToken)->postJson('/api/orders', [
            'customer_name' => 'Budi',
            'customer_phone' => '08123456789',
            'order_date' => now()->toDateString(),
            'items' => [['product_unit_id' => $this->unit->id, 'quantity' => 2]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('user_id', $this->staff->id);

        $this->assertDatabaseHas('order_items', ['product_name' => $this->product->nama, 'satuan' => $this->unit->satuan]);
    }

    public function test_staff_only_sees_own_orders(): void
    {
        Order::factory()->create(['user_id' => $this->staff->id]);
        Order::factory()->create(['user_id' => $this->owner->id]);

        $this->withToken($this->staffToken)->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_owner_sees_all_orders(): void
    {
        Order::factory()->count(2)->create(['user_id' => $this->staff->id]);
        Order::factory()->create(['user_id' => $this->owner->id]);

        $this->withToken($this->ownerToken)->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(3);
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
}
