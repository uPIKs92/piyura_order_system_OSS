<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_load_all_staff_report(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->create(['name' => 'Budi']);
        $token = $owner->createToken('test')->plainTextToken;
        $date = '2026-07-15';

        Order::factory()->create([
            'user_id' => $staff->id,
            'order_date' => $date,
            'grand_total' => 50000,
        ]);

        $response = $this->withToken($token)
            ->getJson("/api/reports/staff?staff_id=all&from=2026-07-01&to=2026-07-30");

        $response->assertOk()
            ->assertJsonPath('all', true)
            ->assertJsonPath('total_orders', 1)
            ->assertJsonPath('items.0.staff_name', 'Budi')
            ->assertJsonPath('items.0.total_revenue', 50000);
    }

    public function test_owner_can_load_all_product_report(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->create();
        $product = Product::factory()->create(['nama' => 'Kopi']);
        $token = $owner->createToken('test')->plainTextToken;
        $date = '2026-07-15';

        $order = Order::factory()->create([
            'user_id' => $staff->id,
            'order_date' => $date,
            'grand_total' => 20000,
        ]);
        $unit = $product->units()->first();
        $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 20000,
            'quantity' => 1,
            'subtotal' => 20000,
        ]);

        $this->withToken($token)
            ->getJson('/api/reports/product?product_id=all&from=2026-07-01&to=2026-07-30')
            ->assertOk()
            ->assertJsonPath('all', true)
            ->assertJsonPath('total_orders', 1)
            ->assertJsonPath('items.0.product_name', 'Kopi')
            ->assertJsonPath('items.0.total_revenue', 20000);
    }

    public function test_owner_can_load_daily_trend_report(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->create();
        $productOne = Product::factory()->create();
        $productTwo = Product::factory()->create();
        $token = $owner->createToken('test')->plainTextToken;
        $unitOne = $productOne->units()->first();
        $unitOne->update(['harga_beli' => 10000, 'harga_jual' => 30000]);
        $unitTwo = $productTwo->units()->first();
        $unitTwo->update(['harga_beli' => 20000, 'harga_jual' => 50000]);

        $orderOne = Order::factory()->create([
            'user_id' => $staff->id,
            'order_date' => '2026-07-14',
            'grand_total' => 30000,
        ]);
        $orderOne->items()->create([
            'product_id' => $productOne->id,
            'product_unit_id' => $unitOne->id,
            'product_name' => $productOne->nama,
            'satuan' => $unitOne->satuan,
            'price_snapshot' => 30000,
            'quantity' => 1,
            'subtotal' => 30000,
        ]);

        $orderTwo = Order::factory()->create([
            'user_id' => $staff->id,
            'order_date' => '2026-07-16',
            'grand_total' => 50000,
        ]);
        $orderTwo->items()->create([
            'product_id' => $productTwo->id,
            'product_unit_id' => $unitTwo->id,
            'product_name' => $productTwo->nama,
            'satuan' => $unitTwo->satuan,
            'price_snapshot' => 50000,
            'quantity' => 1,
            'subtotal' => 50000,
        ]);

        $this->withToken($token)
            ->getJson('/api/reports/daily-trend?from=2026-07-14&to=2026-07-16')
            ->assertOk()
            ->assertJsonPath('period.from', '2026-07-14')
            ->assertJsonPath('period.to', '2026-07-16')
            ->assertJsonPath('items.0.date', '2026-07-14')
            ->assertJsonPath('items.0.total_orders', 1)
            ->assertJsonPath('items.0.total_revenue', 30000)
            ->assertJsonPath('items.0.sales_turnover', 30000)
            ->assertJsonPath('items.0.purchasing_cost', 10000)
            ->assertJsonPath('items.0.net_profit', 20000)
            ->assertJsonPath('items.1.date', '2026-07-15')
            ->assertJsonPath('items.1.total_orders', 0)
            ->assertJsonPath('items.2.date', '2026-07-16')
            ->assertJsonPath('items.2.total_revenue', 50000)
            ->assertJsonPath('items.2.sales_turnover', 50000)
            ->assertJsonPath('items.2.purchasing_cost', 20000)
            ->assertJsonPath('items.2.net_profit', 30000)
            ->assertJsonPath('totals.sales_turnover', 80000)
            ->assertJsonPath('totals.purchasing_cost', 30000)
            ->assertJsonPath('totals.net_profit', 50000);
    }
}
