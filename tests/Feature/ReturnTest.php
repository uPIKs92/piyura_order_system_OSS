<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_return_on_completed_order(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;
        $product = Product::factory()->create();
        $unit = $product->units()->first();
        $unit->update(['stok' => 10, 'harga_jual' => 5000]);
        $order = Order::factory()->create([
            'user_id' => $owner->id,
            'status' => OrderStatus::Selesai,
            'grand_total' => 10000,
        ]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 5000,
            'quantity' => 2,
            'subtotal' => 10000,
        ]);

        $response = $this->withToken($token)
            ->postJson("/api/orders/{$order->id}/returns", [
                'reason' => 'Barang rusak',
                'items' => [
                    ['order_item_id' => $item->id, 'quantity' => 1, 'reason' => 'Cacat'],
                ],
            ])
            ->assertCreated()
            ->assertJsonStructure(['return_no', 'restock_status', 'refund_amount']);

        $this->assertEquals(5000, (float) $response->json('refund_amount'));

        $this->assertEquals(11, $unit->fresh()->stok);
    }
}
