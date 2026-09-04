<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItemBatch;
use App\Models\Product;
use App\Models\StockReceiptLine;
use Database\Factories\SupplierFactory;
use App\Models\User;
use Database\Factories\ProductBatchFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BatchOrderFlowTest extends TestCase
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
        $this->product->units()->first()->update(['stok' => 0, 'harga_jual' => 10000]);
    }

    public function test_draft_to_pending_allocates_fefo_and_snapshots_batches(): void
    {
        $unit = $this->product->units()->first();
        $unit->update(['stok' => 20]);

        $earliest = ProductBatchFactory::new()->expiringIn(5)->create(['product_unit_id' => $unit->id, 'qty' => 10]);
        $undated = ProductBatchFactory::new()->create(['product_unit_id' => $unit->id, 'qty' => 10]);

        $order = $this->createDraftOrder(4);

        $this->submit($order)
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        $this->assertSame(6, (int) $earliest->fresh()->qty);
        $this->assertSame(10, (int) $undated->fresh()->qty);
        $this->assertSame(16, (int) $unit->fresh()->stok);

        $this->assertDatabaseHas('order_item_batches', [
            'order_item_id' => $order->fresh()->items->first()->id,
            'product_batch_id' => $earliest->id,
            'quantity' => 4,
        ]);
    }

    public function test_allocation_splits_across_two_batches(): void
    {
        $unit = $this->product->units()->first();
        $unit->update(['stok' => 5]);

        $first = ProductBatchFactory::new()->expiringIn(5)->create(['product_unit_id' => $unit->id, 'qty' => 3]);
        $second = ProductBatchFactory::new()->expiringIn(30)->create(['product_unit_id' => $unit->id, 'qty' => 2]);

        $order = $this->createDraftOrder(5);

        $this->submit($order)->assertOk();

        $orderItemId = $order->fresh()->items->first()->id;

        $this->assertDatabaseCount('order_item_batches', 2);
        $this->assertDatabaseHas('order_item_batches', [
            'order_item_id' => $orderItemId,
            'product_batch_id' => $first->id,
            'quantity' => 3,
        ]);
        $this->assertDatabaseHas('order_item_batches', [
            'order_item_id' => $orderItemId,
            'product_batch_id' => $second->id,
            'quantity' => 2,
        ]);

        $this->assertSame(0, (int) $first->fresh()->qty);
        $this->assertSame(0, (int) $second->fresh()->qty);
        $this->assertSame(0, (int) $unit->fresh()->stok);
    }

    public function test_submit_fails_when_unexpired_stock_insufficient(): void
    {
        $unit = $this->product->units()->first();
        $unit->update(['stok' => 12]);

        $expired = ProductBatchFactory::new()->expired()->create(['product_unit_id' => $unit->id, 'qty' => 10]);
        $unexpired = ProductBatchFactory::new()->expiringIn(10)->create(['product_unit_id' => $unit->id, 'qty' => 2]);

        $order = $this->createDraftOrder(5);

        $this->submit($order)
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => sprintf(
                    'Stok %s tidak cukup (stok belum kedaluwarsa: 2, dibutuhkan: 5). Stok kedaluwarsa: 10 — lakukan write-off atau restock.',
                    $this->product->nama,
                ),
            ]);

        $this->assertSame(OrderStatus::Draft, $order->fresh()->status);
        $this->assertDatabaseCount('order_item_batches', 0);

        $this->assertSame(10, (int) $expired->fresh()->qty);
        $this->assertSame(2, (int) $unexpired->fresh()->qty);
        $this->assertSame(12, (int) $unit->fresh()->stok);
    }

    public function test_cancel_restores_batch_quantities_per_allocation(): void
    {
        $unit = $this->product->units()->first();
        $unit->update(['stok' => 5]);

        $first = ProductBatchFactory::new()->expiringIn(5)->create(['product_unit_id' => $unit->id, 'qty' => 3]);
        $second = ProductBatchFactory::new()->expiringIn(30)->create(['product_unit_id' => $unit->id, 'qty' => 2]);

        $order = $this->createDraftOrder(5);
        $this->submit($order)->assertOk();

        $this->assertSame(0, (int) $first->fresh()->qty);
        $this->assertSame(0, (int) $second->fresh()->qty);
        $this->assertSame(0, (int) $unit->fresh()->stok);

        $this->withToken($this->token)->putJson("/api/orders/{$order->id}", [
            'version' => $order->fresh()->version,
            'status' => 'cancelled',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $this->assertSame(3, (int) $first->fresh()->qty);
        $this->assertSame(2, (int) $second->fresh()->qty);
        $this->assertSame(5, (int) $unit->fresh()->stok);
    }

    public function test_return_restocks_batches_per_allocation(): void
    {
        $unit = $this->product->units()->first();

        $first = ProductBatchFactory::new()->expiringIn(5)->create(['product_unit_id' => $unit->id, 'qty' => 0]);
        $second = ProductBatchFactory::new()->expiringIn(30)->create(['product_unit_id' => $unit->id, 'qty' => 0]);

        $order = Order::factory()->create([
            'user_id' => $this->owner->id,
            'status' => OrderStatus::Selesai,
        ]);
        $item = $order->items()->create([
            'product_id' => $this->product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $this->product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 10000,
            'quantity' => 5,
            'subtotal' => 50000,
        ]);

        OrderItemBatch::create(['order_item_id' => $item->id, 'product_batch_id' => $first->id, 'quantity' => 3]);
        OrderItemBatch::create(['order_item_id' => $item->id, 'product_batch_id' => $second->id, 'quantity' => 2]);

        $this->withToken($this->token)
            ->postJson("/api/orders/{$order->id}/returns", [
                'reason' => 'Barang rusak',
                'items' => [
                    ['order_item_id' => $item->id, 'quantity' => 2, 'reason' => 'Cacat'],
                ],
            ])
            ->assertCreated();

        $this->assertSame(2, (int) $first->fresh()->qty);
        $this->assertSame(0, (int) $second->fresh()->qty);
        $this->assertSame(2, (int) $unit->fresh()->stok);
    }

    public function test_return_overflow_creates_undated_batch_for_legacy_item(): void
    {
        $unit = $this->product->units()->first();

        $order = Order::factory()->create([
            'user_id' => $this->owner->id,
            'status' => OrderStatus::Selesai,
        ]);
        $item = $order->items()->create([
            'product_id' => $this->product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $this->product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 10000,
            'quantity' => 3,
            'subtotal' => 30000,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/orders/{$order->id}/returns", [
                'reason' => 'Salah kirim',
                'items' => [
                    ['order_item_id' => $item->id, 'quantity' => 3],
                ],
            ])
            ->assertCreated();

        $this->assertSame(3, (int) $unit->fresh()->stok);
        $this->assertDatabaseHas('product_batches', [
            'product_unit_id' => $unit->id,
            'batch_no' => null,
            'expired_at' => null,
            'qty' => 3,
        ]);
    }

    public function test_receipt_with_expiry_and_batch_no_creates_and_merges_batches(): void
    {
        $unit = $this->product->units()->first();
        $supplier = SupplierFactory::new()->create();

        $response = $this->withToken($this->token)
            ->postJson('/api/inventory/receipts', [
                'supplier_name' => 'Supplier ABC',
                'supplier_id' => $supplier->id,
                'items' => [
                    ['product_unit_id' => $unit->id, 'quantity' => 5, 'unit_cost' => 5000, 'expired_at' => '2026-09-01', 'batch_no' => 'B1'],
                ],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('stock_receipts', [
            'id' => $response->json('id'),
            'supplier_id' => $supplier->id,
            'supplier_name' => 'Supplier ABC',
        ]);

        $line = StockReceiptLine::query()->where('product_unit_id', $unit->id)->first();
        $this->assertSame(5, (int) $line->quantity);
        $this->assertSame('2026-09-01', $line->expired_at->toDateString());
        $this->assertSame('B1', $line->batch_no);

        $batch = $line->productUnit->batches()->first();
        $this->assertSame(5, (int) $batch->qty);
        $this->assertSame('B1', $batch->batch_no);
        $this->assertSame('2026-09-01', $batch->expired_at->toDateString());

        $this->withToken($this->token)
            ->postJson('/api/inventory/receipts', [
                'items' => [
                    ['product_unit_id' => $unit->id, 'quantity' => 3, 'expired_at' => '2026-09-01', 'batch_no' => 'B1'],
                ],
            ])
            ->assertCreated();

        $this->assertSame(1, $unit->batches()->count());
        $this->assertSame(8, (int) $batch->fresh()->qty);
        $this->assertSame(8, (int) $unit->fresh()->stok);
    }

    public function test_quick_restock_creates_dated_or_undated_batch(): void
    {
        $unit = $this->product->units()->first();
        $unit->update(['stok' => 3]);

        $this->withToken($this->token)
            ->postJson("/api/product-units/{$unit->id}/restock", [
                'quantity' => 5,
                'notes' => 'Restok gudang',
                'expired_at' => '2026-10-01',
                'batch_no' => 'RX1',
            ])
            ->assertOk()
            ->assertJsonPath('unit.stok', 8);

        $dated = $unit->batches()->first();
        $this->assertSame(5, (int) $dated->qty);
        $this->assertSame('RX1', $dated->batch_no);
        $this->assertSame('2026-10-01', $dated->expired_at->toDateString());

        $this->withToken($this->token)
            ->postJson("/api/product-units/{$unit->id}/restock", [
                'quantity' => 2,
            ])
            ->assertOk();

        $this->assertDatabaseHas('product_batches', [
            'product_unit_id' => $unit->id,
            'batch_no' => null,
            'expired_at' => null,
            'qty' => 2,
        ]);
        $this->assertSame(10, (int) $unit->fresh()->stok);
    }

    public function test_legacy_unit_without_batches_is_sold_via_lazy_backfill(): void
    {
        $unit = $this->product->units()->first();
        $unit->update(['stok' => 10]);

        $order = $this->createDraftOrder(2);

        $this->submit($order)
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        $this->assertSame(8, (int) $unit->fresh()->stok);

        $batch = $unit->batches()->first();
        $this->assertNotNull($batch);
        $this->assertNull($batch->batch_no);
        $this->assertNull($batch->expired_at);
        $this->assertSame(8, (int) $batch->qty);

        $this->assertDatabaseHas('order_item_batches', [
            'order_item_id' => $order->fresh()->items->first()->id,
            'product_batch_id' => $batch->id,
            'quantity' => 2,
        ]);
    }

    private function createDraftOrder(int $quantity): Order
    {
        $unit = $this->product->units()->first();

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
