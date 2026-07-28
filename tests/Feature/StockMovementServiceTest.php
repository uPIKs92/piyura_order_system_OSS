<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_decrements_stock_and_logs_movement(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;
        $product = Product::factory()->create();
        $unit = $product->units()->first();
        $unit->update(['stok' => 20]);

        $order = $this->withToken($token)->postJson('/api/orders', [
            'customer_name' => 'Budi',
            'order_date' => now()->toDateString(),
            'items' => [['product_unit_id' => $unit->id, 'quantity' => 3]],
        ])->assertCreated()->json();

        $this->withToken($token)
            ->putJson("/api/orders/{$order['id']}", [
                'version' => 1,
                'status' => 'pending',
            ])
            ->assertOk();

        $this->assertEquals(17, $unit->fresh()->stok);
        $this->assertDatabaseHas('stock_movements', [
            'product_unit_id' => $unit->id,
            'type' => StockMovementType::Sale->value,
            'quantity_delta' => -3,
            'quantity_before' => 20,
            'quantity_after' => 17,
        ]);
    }

    public function test_cancel_from_pending_restores_stock(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;
        $product = Product::factory()->create();
        $unit = $product->units()->first();
        $unit->update(['stok' => 10]);

        $order = Order::factory()->create([
            'user_id' => $staff->id,
            'status' => OrderStatus::Pending,
            'version' => 1,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 5000,
            'quantity' => 4,
            'subtotal' => 20000,
        ]);
        $unit->update(['stok' => 6]);

        $this->withToken($token)
            ->putJson("/api/orders/{$order->id}", [
                'version' => 1,
                'status' => 'cancelled',
                'reason' => 'Batal',
            ])
            ->assertOk();

        $this->assertEquals(10, $unit->fresh()->stok);
        $this->assertDatabaseHas('stock_movements', [
            'product_unit_id' => $unit->id,
            'type' => StockMovementType::CancelRestore->value,
            'quantity_delta' => 4,
        ]);
    }
}
