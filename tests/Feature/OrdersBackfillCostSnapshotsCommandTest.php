<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdersBackfillCostSnapshotsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_populates_cost_snapshot_from_product_unit(): void
    {
        $owner = User::factory()->owner()->create();
        $product = Product::factory()->create(['tenant_id' => $owner->tenant_id]);
        $unit = $product->units()->first();
        $unit->update(['harga_beli' => 12000, 'harga_jual' => 20000]);

        $order = Order::factory()->create(['user_id' => $owner->id]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 20000,
            'cost_snapshot' => 0,
            'quantity' => 2,
            'subtotal' => 40000,
        ]);

        $this->artisan('orders:backfill-cost-snapshots')
            ->assertSuccessful();

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'cost_snapshot' => 12000,
        ]);
    }

    public function test_backfill_skips_rows_with_existing_snapshot(): void
    {
        $owner = User::factory()->owner()->create();
        $product = Product::factory()->create(['tenant_id' => $owner->tenant_id]);
        $unit = $product->units()->first();
        $unit->update(['harga_beli' => 12000, 'harga_jual' => 20000]);

        $order = Order::factory()->create(['user_id' => $owner->id]);

        $kept = $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 20000,
            'cost_snapshot' => 5000,
            'quantity' => 1,
            'subtotal' => 20000,
        ]);
        $backfilled = $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 20000,
            'cost_snapshot' => 0,
            'quantity' => 1,
            'subtotal' => 20000,
        ]);

        $this->artisan('orders:backfill-cost-snapshots')
            ->assertSuccessful()
            ->expectsOutputToContain('updated 1 order items');

        $this->assertSame(5000.0, (float) $kept->fresh()->cost_snapshot);
        $this->assertSame(12000.0, (float) $backfilled->fresh()->cost_snapshot);
    }
}
