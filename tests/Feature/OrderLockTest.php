<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLockTest extends TestCase
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

    public function test_diproses_order_rejects_customer_name_edit(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->staff->id,
            'customer_name' => 'Budi',
            'status' => OrderStatus::Diproses,
        ]);

        $this->withToken($this->staffToken)->putJson("/api/orders/{$order->id}", [
            'version' => $order->version,
            'customer_name' => 'Changed',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_name' => 'Budi',
            'status' => 'diproses',
            'version' => $order->version,
        ]);
    }

    public function test_diproses_order_allows_status_only_transition_to_dikirim(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->staff->id,
            'status' => OrderStatus::Diproses,
        ]);

        $this->withToken($this->staffToken)->putJson("/api/orders/{$order->id}", [
            'version' => $order->version,
            'status' => 'dikirim',
            'reason' => 'Pesanan diserahkan ke kurir',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'dikirim');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'dikirim',
            'version' => $order->version + 1,
        ]);
    }

    public function test_draft_order_still_accepts_full_edit(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->staff->id,
            'status' => OrderStatus::Draft,
        ]);

        $this->withToken($this->staffToken)->putJson("/api/orders/{$order->id}", [
            'version' => $order->version,
            'customer_name' => 'Budi',
            'customer_phone' => '08123456789',
            'order_date' => now()->toDateString(),
            'notes' => 'Catatan baru',
            'items' => [[
                'product_unit_id' => $this->unit->id,
                'quantity' => 2,
                'discount_type' => 'fixed',
                'discount_value' => 0,
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('customer_name', 'Budi')
            ->assertJsonPath('notes', 'Catatan baru');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'notes' => 'Catatan baru',
        ]);
    }

    public function test_pending_order_still_accepts_full_edit(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->staff->id,
            'status' => OrderStatus::Pending,
            'notes' => 'Lama',
        ]);

        $this->withToken($this->staffToken)->putJson("/api/orders/{$order->id}", [
            'version' => $order->version,
            'customer_name' => 'Budi',
            'notes' => 'Catatan baru',
        ])
            ->assertOk()
            ->assertJsonPath('customer_name', 'Budi')
            ->assertJsonPath('notes', 'Catatan baru');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_name' => 'Budi',
            'notes' => 'Catatan baru',
        ]);
    }

    public function test_cancelled_order_rejects_extra_fields(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->staff->id,
            'notes' => 'Original',
            'status' => OrderStatus::Cancelled,
        ]);

        $this->withToken($this->staffToken)->putJson("/api/orders/{$order->id}", [
            'version' => $order->version,
            'notes' => 'Changed',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'notes' => 'Original',
            'status' => 'cancelled',
            'version' => $order->version,
        ]);
    }
}
