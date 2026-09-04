<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use Database\Factories\ProductBatchFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OrderDeleteStockRestoreTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private string $token;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
        $this->token = $this->owner->createToken('test')->plainTextToken;
        $this->product = Product::factory()->create();
    }

    public function test_soft_delete_pending_order_restores_stock_and_batches(): void
    {
        $unit = $this->product->units()->first();
        $unit->update(['stok' => 10]);

        $earliest = ProductBatchFactory::new()->expiringIn(5)->create(['product_unit_id' => $unit->id, 'qty' => 3]);
        $latest = ProductBatchFactory::new()->expiringIn(30)->create(['product_unit_id' => $unit->id, 'qty' => 7]);

        $order = $this->createDraftOrder($unit, 5);
        $this->submit($order)->assertOk();

        $this->assertSame(5, (int) $unit->fresh()->stok);
        $this->assertSame(0, (int) $earliest->fresh()->qty);
        $this->assertSame(5, (int) $latest->fresh()->qty);

        $this->withToken($this->token)->deleteJson("/api/orders/{$order->id}")->assertOk();

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
        $this->assertSame(10, (int) $unit->fresh()->stok);
        $this->assertSame(3, (int) $earliest->fresh()->qty);
        $this->assertSame(7, (int) $latest->fresh()->qty);

        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseHas('stock_movements', [
            'product_unit_id' => $unit->id,
            'type' => StockMovementType::DeleteRestore->value,
            'quantity_delta' => 5,
            'quantity_before' => 5,
            'quantity_after' => 10,
        ]);
    }

    public function test_force_delete_pending_order_restores_stock_and_batches(): void
    {
        $unit = $this->product->units()->first();
        $unit->update(['stok' => 8]);

        $batch = ProductBatchFactory::new()->expiringIn(5)->create(['product_unit_id' => $unit->id, 'qty' => 8]);

        $order = $this->createDraftOrder($unit, 3);
        $this->submit($order)->assertOk();

        $this->assertSame(5, (int) $unit->fresh()->stok);
        $this->assertSame(5, (int) $batch->fresh()->qty);

        $this->withToken($this->token)
            ->deleteJson("/api/orders/{$order->id}?force=1")
            ->assertOk();

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertSame(8, (int) $unit->fresh()->stok);
        $this->assertSame(8, (int) $batch->fresh()->qty);

        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseHas('stock_movements', [
            'product_unit_id' => $unit->id,
            'type' => StockMovementType::DeleteRestore->value,
            'quantity_delta' => 3,
            'quantity_before' => 5,
            'quantity_after' => 8,
        ]);
    }

    public function test_soft_delete_draft_order_does_not_restore_stock(): void
    {
        $unit = $this->product->units()->first();
        $unit->update(['stok' => 10]);

        $order = $this->createDraftOrder($unit, 4);

        $this->withToken($this->token)->deleteJson("/api/orders/{$order->id}")->assertOk();

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
        $this->assertSame(10, (int) $unit->fresh()->stok);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_soft_delete_cancelled_order_does_not_restore_stock_again(): void
    {
        $unit = $this->product->units()->first();
        $unit->update(['stok' => 10]);

        $order = $this->createDraftOrder($unit, 4);
        $this->submit($order)->assertOk();

        $this->withToken($this->token)->putJson("/api/orders/{$order->id}", [
            'version' => $order->fresh()->version,
            'status' => 'cancelled',
            'reason' => 'Batal',
        ])->assertOk();

        $this->assertSame(10, (int) $unit->fresh()->stok);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseHas('stock_movements', [
            'product_unit_id' => $unit->id,
            'type' => StockMovementType::CancelRestore->value,
        ]);

        $this->withToken($this->token)->deleteJson("/api/orders/{$order->id}")->assertOk();

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
        $this->assertSame(10, (int) $unit->fresh()->stok);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseMissing('stock_movements', [
            'type' => StockMovementType::DeleteRestore->value,
        ]);
    }

    private function createDraftOrder(ProductUnit $unit, int $quantity): Order
    {
        $order = Order::factory()->create([
            'user_id' => $this->owner->id,
            'status' => OrderStatus::Draft,
        ]);
        $order->items()->create([
            'product_id' => $this->product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $this->product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 10000,
            'quantity' => $quantity,
            'subtotal' => 10000 * $quantity,
        ]);

        return $order;
    }

    private function submit(Order $order): TestResponse
    {
        return $this->withToken($this->token)->putJson("/api/orders/{$order->id}", [
            'version' => $order->version,
            'status' => 'pending',
        ]);
    }
}
