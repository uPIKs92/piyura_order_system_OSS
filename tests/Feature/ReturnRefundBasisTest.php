<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnRefundBasisTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_return_of_discounted_item_nets_to_zero_on_product_report(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $product = $this->createCompletedOrderWithDiscountedReturn(
            $owner, '2026-07-14', '2026-07-15', 10, 10, 10000, 80000,
        );

        $this->withToken($token)
            ->getJson('/api/reports/product?product_id=all&from=2026-07-01&to=2026-07-31')
            ->assertOk()
            ->assertJsonPath('items.0.total_revenue', 80000)
            ->assertJsonPath('items.0.returned_qty', 10)
            ->assertJsonPath('items.0.returns_total', 80000)
            ->assertJsonPath('items.0.net_revenue', 0)
            ->assertJsonPath('total_orders', 1)
            ->assertJsonPath('returns_total', 80000)
            ->assertJsonPath('net_revenue', 0);

        $this->withToken($token)
            ->getJson("/api/reports/product?product_id={$product->id}&from=2026-07-01&to=2026-07-31")
            ->assertOk()
            ->assertJsonPath('total_revenue', 80000)
            ->assertJsonPath('returned_qty', 10)
            ->assertJsonPath('returns_total', 80000)
            ->assertJsonPath('net_qty', 0)
            ->assertJsonPath('net_revenue', 0);
    }

    public function test_full_return_daily_net_uses_discounted_basis_on_return_date(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->createCompletedOrderWithDiscountedReturn(
            $owner, '2026-07-14', '2026-07-15', 10, 10, 10000, 80000,
        );

        $this->withToken($token)
            ->getJson('/api/reports/daily?date=2026-07-15')
            ->assertOk()
            ->assertJsonPath('total_revenue', 0)
            ->assertJsonPath('returns_total', 80000)
            ->assertJsonPath('net_revenue', -80000);

        $this->withToken($token)
            ->getJson('/api/reports/daily?date=2026-07-14')
            ->assertOk()
            ->assertJsonPath('total_revenue', 80000)
            ->assertJsonPath('returns_total', 0)
            ->assertJsonPath('net_revenue', 80000);

        $this->withToken($token)
            ->getJson('/api/reports/daily-trend?from=2026-07-14&to=2026-07-15')
            ->assertOk()
            ->assertJsonPath('items.0.returns_total', 0)
            ->assertJsonPath('items.0.net_revenue', 80000)
            ->assertJsonPath('items.1.returns_total', 80000)
            ->assertJsonPath('items.1.net_revenue', -80000)
            ->assertJsonPath('totals.total_revenue', 80000)
            ->assertJsonPath('totals.returns_total', 80000)
            ->assertJsonPath('totals.net_revenue', 0);
    }

    public function test_partial_return_of_discounted_item_uses_effective_unit_price(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $product = $this->createCompletedOrderWithDiscountedReturn(
            $owner, '2026-07-14', '2026-07-15', 10, 3, 10000, 80000,
        );

        $this->withToken($token)
            ->getJson("/api/reports/product?product_id={$product->id}&from=2026-07-01&to=2026-07-31")
            ->assertOk()
            ->assertJsonPath('total_revenue', 80000)
            ->assertJsonPath('returned_qty', 3)
            ->assertJsonPath('returns_total', 24000)
            ->assertJsonPath('net_qty', 7)
            ->assertJsonPath('net_revenue', 56000);

        $this->withToken($token)
            ->getJson('/api/reports/product?product_id=all&from=2026-07-01&to=2026-07-31')
            ->assertOk()
            ->assertJsonPath('items.0.returns_total', 24000)
            ->assertJsonPath('items.0.net_revenue', 56000);

        $this->withToken($token)
            ->getJson('/api/reports/daily?date=2026-07-15')
            ->assertOk()
            ->assertJsonPath('returns_total', 24000)
            ->assertJsonPath('net_revenue', -24000);
    }

    public function test_return_api_writes_discounted_refund_amount(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;
        $product = Product::factory()->create(['nama' => 'Teh Diskon']);
        $unit = $product->units()->first();
        $unit->update(['stok' => 10]);

        $order = Order::factory()->create([
            'user_id' => $owner->id,
            'order_date' => '2026-07-14',
            'subtotal' => 80000,
            'discount_total' => 20000,
            'grand_total' => 80000,
            'status' => OrderStatus::Selesai,
        ]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 10000,
            'quantity' => 10,
            'discount_type' => 'percent',
            'discount_value' => 20,
            'subtotal' => 80000,
        ]);

        $response = $this->withToken($token)
            ->postJson("/api/orders/{$order->id}/returns", [
                'reason' => 'Barang rusak',
                'items' => [
                    ['order_item_id' => $item->id, 'quantity' => 3, 'reason' => 'Cacat'],
                ],
            ])
            ->assertCreated();

        $this->assertEquals(24000, (float) $response->json('refund_amount'));
        $this->assertEquals(24000, (float) OrderReturn::query()->where('order_id', $order->id)->value('refund_amount'));
    }

    /**
     * Item with a 20% discount: list price 10000 x 10, subtotal 80000.
     * Refund basis mirrors ReportService: ROUND(subtotal * returned / qty, 2).
     */
    private function createCompletedOrderWithDiscountedReturn(
        User $owner,
        string $orderDate,
        string $returnDate,
        int $quantity,
        int $returnedQuantity,
        int $listPrice,
        int $subtotal,
    ): Product {
        $staff = User::factory()->create();
        $product = Product::factory()->create(['nama' => 'Kopi Diskon']);
        $unit = $product->units()->first();

        $order = Order::factory()->create([
            'user_id' => $staff->id,
            'order_date' => $orderDate,
            'subtotal' => $subtotal,
            'discount_total' => $listPrice * $quantity - $subtotal,
            'grand_total' => $subtotal,
            'status' => OrderStatus::Selesai,
        ]);
        $orderItem = $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => $listPrice,
            'quantity' => $quantity,
            'discount_type' => 'percent',
            'discount_value' => 20,
            'subtotal' => $subtotal,
        ]);

        $refund = round($subtotal * $returnedQuantity / $quantity, 2);
        $return = OrderReturn::create([
            'tenant_id' => $owner->tenant_id,
            'order_id' => $order->id,
            'return_no' => 'RET-'.str_replace('-', '', $returnDate).'-'.$order->id,
            'reason' => 'Barang rusak',
            'refund_amount' => $refund,
            'restock_status' => true,
            'created_by' => $owner->id,
        ]);
        $return->created_at = Carbon::parse($returnDate.' 10:00:00');
        $return->save();
        ReturnItem::create([
            'return_id' => $return->id,
            'order_item_id' => $orderItem->id,
            'quantity' => $returnedQuantity,
            'reason' => 'Cacat',
            'created_at' => Carbon::parse($returnDate.' 10:00:00'),
        ]);

        return $product;
    }
}
