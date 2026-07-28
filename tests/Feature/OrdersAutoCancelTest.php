<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdersAutoCancelTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_orders_older_than_7_days_are_cancelled(): void
    {
        $staff = User::factory()->create();
        $product = Product::factory()->create();
        $unit = $product->units()->first();
        $unit->update(['stok' => 20]);
        $order = Order::factory()->pending()->create([
            'user_id' => $staff->id,
            'created_at' => now()->subDays(8),
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 1000,
            'quantity' => 5,
            'subtotal' => 5000,
        ]);
        $unit->update(['stok' => 15]);

        $this->artisan('orders:auto-cancel')->assertSuccessful();

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertEquals(20, $unit->fresh()->stok);
        $this->assertDatabaseHas('order_status_logs', [
            'order_id' => $order->id,
            'reason' => 'Auto-cancel: expired 7 hari',
        ]);
    }
}
