<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use App\Support\TenantSettings;
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
            'status' => OrderStatus::Pending,
            'grand_total' => 50000,
        ]);

        $response = $this->withToken($token)
            ->getJson('/api/reports/staff?staff_id=all&from=2026-07-01&to=2026-07-30');

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
            'status' => OrderStatus::Pending,
            'subtotal' => 40000,
            'grand_total' => 40000,
        ]);
        $unit = $product->units()->first();
        $satuan = $unit->satuan;
        $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $satuan,
            'price_snapshot' => 20000,
            'cost_snapshot' => 8000,
            'quantity' => 2,
            'subtotal' => 40000,
        ]);

        $this->withToken($token)
            ->getJson('/api/reports/product?product_id=all&from=2026-07-01&to=2026-07-30')
            ->assertOk()
            ->assertJsonPath('all', true)
            ->assertJsonPath('total_orders', 1)
            ->assertJsonPath('total_qty', 2)
            ->assertJsonPath('total_cost', 16000)
            ->assertJsonPath('net_profit', 24000)
            ->assertJsonPath('items.0.product_name', 'Kopi')
            ->assertJsonPath('items.0.satuan', $satuan)
            ->assertJsonPath('items.0.total_revenue', 40000)
            ->assertJsonPath('items.0.total_qty', 2)
            ->assertJsonPath('items.0.total_cost', 16000)
            ->assertJsonPath('items.0.net_profit', 24000);
    }

    public function test_all_product_report_ranks_by_net_qty_desc(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $alpha = Product::factory()->create(['nama' => 'Alpha']);
        $beta = Product::factory()->create(['nama' => 'Beta']);
        $gamma = Product::factory()->create(['nama' => 'Gamma']);

        // Alpha: qty 2, revenue 20k. Beta: qty 2, revenue 50k. Gamma: qty 5.
        // Expected rank: Gamma (qty), then Beta (tie on qty, higher revenue), then Alpha.
        $this->createOrderWithItem($staff, $alpha, '2026-07-15', 2, 10000);
        $this->createOrderWithItem($staff, $beta, '2026-07-15', 2, 25000);
        $this->createOrderWithItem($staff, $gamma, '2026-07-15', 5, 10000);

        $this->withToken($token)
            ->getJson('/api/reports/product?product_id=all&from=2026-07-01&to=2026-07-30')
            ->assertOk()
            ->assertJsonPath('items.0.product_name', 'Gamma')
            ->assertJsonPath('items.0.net_qty', 5)
            ->assertJsonPath('items.1.product_name', 'Beta')
            ->assertJsonPath('items.1.net_qty', 2)
            ->assertJsonPath('items.2.product_name', 'Alpha')
            ->assertJsonPath('items.2.net_qty', 2);
    }

    public function test_all_product_report_includes_previous_period_deltas(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->create();
        $product = Product::factory()->create(['nama' => 'Kopi']);
        $token = $owner->createToken('test')->plainTextToken;

        // Current window 2026-07-01..2026-07-30 (30 days); previous = 2026-06-01..2026-06-30.
        $this->createOrderWithItem($staff, $product, '2026-07-15', 3, 20000);
        $this->createOrderWithItem($staff, $product, '2026-06-15', 2, 20000);

        $response = $this->withToken($token)
            ->getJson('/api/reports/product?product_id=all&from=2026-07-01&to=2026-07-30');
        $response->assertOk();

        $item = $response->json('items.0');
        $this->assertSame(2, $item['prev_net_qty']);
        $this->assertSame(40000.0, (float) $item['prev_net_revenue']);
        $this->assertEqualsWithDelta(50.0, $item['delta_qty_pct'], 0.001);
        $this->assertEqualsWithDelta(50.0, $item['delta_revenue_pct'], 0.001);
    }

    public function test_all_product_report_marks_new_products_with_null_deltas(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->create();
        $product = Product::factory()->create(['nama' => 'Baru']);
        $token = $owner->createToken('test')->plainTextToken;

        $this->createOrderWithItem($staff, $product, '2026-07-15', 4, 20000);

        $this->withToken($token)
            ->getJson('/api/reports/product?product_id=all&from=2026-07-01&to=2026-07-30')
            ->assertOk()
            ->assertJsonPath('items.0.prev_net_qty', 0)
            ->assertJsonPath('items.0.prev_net_revenue', 0)
            ->assertJsonPath('items.0.delta_qty_pct', null)
            ->assertJsonPath('items.0.delta_revenue_pct', null);
    }

    private function createOrderWithItem(User $staff, Product $product, string $date, int $quantity, int $unitPrice = 20000, int $unitCost = 8000): Order
    {
        $unit = $product->units()->first();
        $order = Order::factory()->create([
            'user_id' => $staff->id,
            'order_date' => $date,
            'status' => OrderStatus::Pending,
            'subtotal' => $unitPrice * $quantity,
            'grand_total' => $unitPrice * $quantity,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => $unitPrice,
            'cost_snapshot' => $unitCost,
            'quantity' => $quantity,
            'subtotal' => $unitPrice * $quantity,
        ]);

        return $order;
    }

    public function test_owner_can_load_single_product_report(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->create();
        $product = Product::factory()->create(['nama' => 'Teh']);
        $token = $owner->createToken('test')->plainTextToken;
        $unit = $product->units()->first();

        $orderOne = Order::factory()->create([
            'user_id' => $staff->id,
            'order_date' => '2026-07-14',
            'status' => OrderStatus::Pending,
            'subtotal' => 40000,
            'grand_total' => 40000,
        ]);
        $orderOne->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 20000,
            'cost_snapshot' => 8000,
            'quantity' => 2,
            'subtotal' => 40000,
        ]);

        $orderTwo = Order::factory()->create([
            'user_id' => $staff->id,
            'order_date' => '2026-07-15',
            'status' => OrderStatus::Pending,
            'subtotal' => 20000,
            'grand_total' => 20000,
        ]);
        $orderTwo->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 20000,
            'cost_snapshot' => 8000,
            'quantity' => 1,
            'subtotal' => 20000,
        ]);

        $this->withToken($token)
            ->getJson("/api/reports/product?product_id={$product->id}&from=2026-07-01&to=2026-07-30")
            ->assertOk()
            ->assertJsonPath('product_id', $product->id)
            ->assertJsonPath('total_orders', 2)
            ->assertJsonPath('total_revenue', 60000)
            ->assertJsonPath('total_qty', 3)
            ->assertJsonPath('total_cost', 24000)
            ->assertJsonPath('net_profit', 36000);
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
            'status' => OrderStatus::Pending,
            'subtotal' => 30000,
            'discount_total' => 2000,
            'grand_total' => 30000,
        ]);
        $orderOne->items()->create([
            'product_id' => $productOne->id,
            'product_unit_id' => $unitOne->id,
            'product_name' => $productOne->nama,
            'satuan' => $unitOne->satuan,
            'price_snapshot' => 30000,
            'cost_snapshot' => 10000,
            'quantity' => 1,
            'subtotal' => 30000,
        ]);

        $orderTwo = Order::factory()->create([
            'user_id' => $staff->id,
            'order_date' => '2026-07-16',
            'status' => OrderStatus::Pending,
            'subtotal' => 50000,
            'discount_total' => 0,
            'grand_total' => 50000,
        ]);
        $orderTwo->items()->create([
            'product_id' => $productTwo->id,
            'product_unit_id' => $unitTwo->id,
            'product_name' => $productTwo->nama,
            'satuan' => $unitTwo->satuan,
            'price_snapshot' => 50000,
            'cost_snapshot' => 20000,
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
            ->assertJsonPath('items.0.gross_subtotal', 30000)
            ->assertJsonPath('items.0.total_discount', 2000)
            ->assertJsonPath('items.0.aov', 30000)
            ->assertJsonPath('items.0.sales_turnover', 30000)
            ->assertJsonPath('items.0.purchasing_cost', 10000)
            ->assertJsonPath('items.0.net_profit', 20000)
            ->assertJsonPath('items.1.date', '2026-07-15')
            ->assertJsonPath('items.1.total_orders', 0)
            ->assertJsonPath('items.1.aov', 0)
            ->assertJsonPath('items.2.date', '2026-07-16')
            ->assertJsonPath('items.2.total_revenue', 50000)
            ->assertJsonPath('items.2.gross_subtotal', 50000)
            ->assertJsonPath('items.2.sales_turnover', 50000)
            ->assertJsonPath('items.2.purchasing_cost', 20000)
            ->assertJsonPath('items.2.net_profit', 30000)
            ->assertJsonPath('totals.sales_turnover', 80000)
            ->assertJsonPath('totals.purchasing_cost', 30000)
            ->assertJsonPath('totals.net_profit', 50000)
            ->assertJsonPath('totals.gross_subtotal', 80000)
            ->assertJsonPath('totals.total_discount', 2000)
            ->assertJsonPath('totals.aov', 40000);
    }

    public function test_daily_trend_uses_cost_snapshot_not_live_product_cost(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->create();
        $product = Product::factory()->create();
        $token = $owner->createToken('test')->plainTextToken;
        $unit = $product->units()->first();
        $unit->update(['harga_beli' => 99999, 'harga_jual' => 30000]);

        $order = Order::factory()->create([
            'user_id' => $staff->id,
            'order_date' => '2026-07-14',
            'status' => OrderStatus::Pending,
            'grand_total' => 30000,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 30000,
            'cost_snapshot' => 10000,
            'quantity' => 1,
            'subtotal' => 30000,
        ]);

        $unit->update(['harga_beli' => 1]);

        $this->withToken($token)
            ->getJson('/api/reports/daily-trend?from=2026-07-14&to=2026-07-14')
            ->assertOk()
            ->assertJsonPath('items.0.purchasing_cost', 10000)
            ->assertJsonPath('items.0.net_profit', 20000);
    }
    public function test_daily_report_returns_zeroed_summary_for_day_without_orders(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/reports/daily?date=2026-07-15')
            ->assertOk()
            ->assertJsonPath('period.from', '2026-07-15')
            ->assertJsonPath('period.to', '2026-07-15')
            ->assertJsonPath('total_orders', 0)
            ->assertJsonPath('total_revenue', 0)
            ->assertJsonPath('total_ppn', 0)
            ->assertJsonPath('by_status', []);
    }

    public function test_all_staff_report_is_empty_for_range_without_orders(): void
    {
        $owner = User::factory()->owner()->create();
        User::factory()->create(['name' => 'Budi']);
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/reports/staff?staff_id=all&from=2026-07-01&to=2026-07-30')
            ->assertOk()
            ->assertJsonPath('all', true)
            ->assertJsonPath('period.from', '2026-07-01')
            ->assertJsonPath('period.to', '2026-07-30')
            ->assertJsonPath('total_orders', 0)
            ->assertJsonPath('total_revenue', 0)
            ->assertJsonPath('total_ppn', 0)
            ->assertJsonPath('by_status', [])
            ->assertJsonPath('items', []);
    }

    public function test_by_staff_report_returns_zeroes_for_staff_without_orders_in_range(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->create(['name' => 'Budi']);
        $otherStaff = User::factory()->create(['name' => 'Adi']);
        Order::factory()->create([
            'user_id' => $otherStaff->id,
            'order_date' => '2026-07-15',
            'grand_total' => 10000,
        ]);
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/reports/staff?staff_id={$staff->id}&from=2026-07-01&to=2026-07-30")
            ->assertOk()
            ->assertJsonPath('period.from', '2026-07-01')
            ->assertJsonPath('period.to', '2026-07-30')
            ->assertJsonPath('total_orders', 0)
            ->assertJsonPath('total_revenue', 0)
            ->assertJsonPath('total_ppn', 0)
            ->assertJsonPath('by_status', []);
    }

    public function test_staff_report_excludes_draft_and_cancelled_orders(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->create(['name' => 'Budi']);
        Order::factory()->create(['user_id' => $staff->id, 'order_date' => '2026-07-14', 'status' => OrderStatus::Pending, 'grand_total' => 10000, 'total_paid' => 4000, 'ppn_amount' => 1100]); // partial paid: unpaid 6000
        Order::factory()->create(['user_id' => $staff->id, 'order_date' => '2026-07-15', 'status' => OrderStatus::Selesai, 'grand_total' => 20000, 'total_paid' => 20000, 'ppn_amount' => 2200]); // fully paid
        Order::factory()->create(['user_id' => $staff->id, 'order_date' => '2026-07-16', 'status' => OrderStatus::Cancelled, 'grand_total' => 99999, 'ppn_amount' => 9999]);
        Order::factory()->create(['user_id' => $staff->id, 'order_date' => '2026-07-16', 'status' => OrderStatus::Draft, 'grand_total' => 4000]); // excluded: Draft
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/reports/staff?staff_id={$staff->id}&from=2026-07-01&to=2026-07-31")
            ->assertOk()
            ->assertJsonPath('total_orders', 2)
            ->assertJsonPath('total_revenue', 30000)
            ->assertJsonPath('total_ppn', 3300)
            ->assertJsonPath('unpaid_count', 1)
            ->assertJsonPath('unpaid_amount', 6000)
            ->assertJsonPath('aov', 15000)
            ->assertJsonPath('by_status.pending', 1)
            ->assertJsonPath('by_status.selesai', 1)
            ->assertJsonMissing(['draft' => 1])
            ->assertJsonMissing(['cancelled' => 1]);

        // Single-day whereBetween range matches nothing because order_date is stored
        // as "2026-07-15 00:00:00" and falls outside ["2026-07-15","2026-07-15"] —
        // pre-existing driver behavior preserved by the SQL rewrite.
        $this->withToken($token)
            ->getJson("/api/reports/staff?staff_id={$staff->id}&from=2026-07-15&to=2026-07-15")
            ->assertOk()
            ->assertJsonPath('total_orders', 0)
            ->assertJsonPath('total_revenue', 0)
            ->assertJsonPath('total_ppn', 0)
            ->assertJsonPath('unpaid_count', 0)
            ->assertJsonPath('unpaid_amount', 0)
            ->assertJsonPath('aov', 0)
            ->assertJsonPath('by_status', []);

        // Single-day reporting goes through the daily endpoint (whereDate) and counts
        // only that day's non-draft, non-cancelled orders.
        $this->withToken($token)
            ->getJson('/api/reports/daily?date=2026-07-15')
            ->assertOk()
            ->assertJsonPath('total_orders', 1)
            ->assertJsonPath('total_revenue', 20000)
            ->assertJsonPath('total_ppn', 2200)
            ->assertJsonPath('by_status.selesai', 1);

        // Empty range (no orders between dates).
        $this->withToken($token)
            ->getJson("/api/reports/staff?staff_id={$staff->id}&from=2026-08-01&to=2026-08-31")
            ->assertOk()
            ->assertJsonPath('total_orders', 0)
            ->assertJsonPath('by_status', []);
    }

    public function test_all_staff_report_excludes_staff_without_orders_and_sorts_by_revenue_desc(): void
    {
        $owner = User::factory()->owner()->create();
        $zaki = User::factory()->create(['name' => 'Zaki']);
        $adi = User::factory()->create(['name' => 'Adi']);
        User::factory()->create(['name' => 'No Orders']);
        Order::factory()->create(['user_id' => $zaki->id, 'order_date' => '2026-07-15', 'status' => OrderStatus::Pending, 'grand_total' => 50000, 'total_paid' => 30000, 'ppn_amount' => 5500]); // partial paid: unpaid 20000
        Order::factory()->create(['user_id' => $zaki->id, 'order_date' => '2026-07-16', 'status' => OrderStatus::Pending, 'grand_total' => 10000, 'total_paid' => 10000]);
        Order::factory()->create(['user_id' => $adi->id, 'order_date' => '2026-07-15', 'status' => OrderStatus::Pending, 'grand_total' => 20000, 'total_paid' => 25000, 'ppn_amount' => 2200]); // overpaid: unpaid clamps to 0
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/reports/staff?staff_id=all&from=2026-07-01&to=2026-07-30')
            ->assertOk()
            ->assertJsonPath('total_orders', 3)
            ->assertJsonPath('total_revenue', 80000)
            ->assertJsonPath('total_ppn', 7700)
            ->assertJsonPath('unpaid_count', 1)
            ->assertJsonPath('unpaid_amount', 20000)
            ->assertJsonPath('items.0.staff_name', 'Zaki')
            ->assertJsonPath('items.0.staff_id', $zaki->id)
            ->assertJsonPath('items.0.total_orders', 2)
            ->assertJsonPath('items.0.total_revenue', 60000)
            ->assertJsonPath('items.0.total_ppn', 5500)
            ->assertJsonPath('items.0.unpaid_count', 1)
            ->assertJsonPath('items.0.unpaid_amount', 20000)
            ->assertJsonPath('items.0.aov', 30000)
            ->assertJsonPath('items.1.staff_name', 'Adi')
            ->assertJsonPath('items.1.staff_id', $adi->id)
            ->assertJsonPath('items.1.total_orders', 1)
            ->assertJsonPath('items.1.total_revenue', 20000)
            ->assertJsonPath('items.1.total_ppn', 2200)
            ->assertJsonPath('items.1.unpaid_count', 0)
            ->assertJsonPath('items.1.unpaid_amount', 0)
            ->assertJsonPath('items.1.aov', 20000)
            ->assertJsonMissing(['staff_name' => 'No Orders']);
    }

    public function test_tax_report_returns_monthly_breakdown_with_fractional_amounts(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->create();
        Order::factory()->create(['user_id' => $staff->id, 'order_date' => '2026-01-10', 'status' => OrderStatus::Selesai, 'grand_total' => 50500.50, 'ppn_amount' => 5500.50]);
        Order::factory()->create(['user_id' => $staff->id, 'order_date' => '2026-01-20', 'status' => OrderStatus::Draft, 'grand_total' => 10100.25, 'ppn_amount' => 1100.25]); // excluded: Draft
        Order::factory()->create(['user_id' => $staff->id, 'order_date' => '2026-02-05', 'status' => OrderStatus::Pending, 'grand_total' => 20200.25, 'ppn_amount' => 1100.25]);
        Order::factory()->create(['user_id' => $staff->id, 'order_date' => '2026-03-05', 'status' => OrderStatus::Cancelled, 'grand_total' => 99999, 'ppn_amount' => 9999]);
        Order::factory()->create(['user_id' => $staff->id, 'order_date' => '2025-06-15', 'status' => OrderStatus::Selesai, 'grand_total' => 77777, 'ppn_amount' => 7777]);
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/reports/tax?year=2026')
            ->assertOk()
            ->assertJsonPath('year', 2026)
            ->assertJsonPath('ppn.enabled', true)
            ->assertJsonPath('ppn.percentage', 11)
            ->assertJsonPath('pph.mode', 'umkm_non_pkp')
            ->assertJsonPath('pph.total', 353.5)
            ->assertJsonPath('totals.orders', 2)
            ->assertJsonPath('totals.omzet', 70700.75)
            ->assertJsonPath('totals.dpp', 64100)
            ->assertJsonPath('totals.ppn', 6600.75)
            ->assertJsonPath('totals.faktur_count', 2)
            ->assertJsonCount(12, 'months')
            ->assertJsonPath('months.0.month', 1)
            ->assertJsonPath('months.0.orders', 1)
            ->assertJsonPath('months.0.omzet', 50500.5)
            ->assertJsonPath('months.0.faktur_count', 1)
            ->assertJsonPath('months.0.dpp', 45000)
            ->assertJsonPath('months.0.ppn', 5500.5)
            ->assertJsonPath('months.0.pph', 252.5)
            ->assertJsonPath('months.1.month', 2)
            ->assertJsonPath('months.1.orders', 1)
            ->assertJsonPath('months.1.omzet', 20200.25)
            ->assertJsonPath('months.1.faktur_count', 1)
            ->assertJsonPath('months.1.dpp', 19100)
            ->assertJsonPath('months.1.ppn', 1100.25)
            ->assertJsonPath('months.1.pph', 101)
            ->assertJsonPath('months.2.month', 3)
            ->assertJsonPath('months.2.orders', 0)
            ->assertJsonPath('months.2.omzet', 0)
            ->assertJsonPath('months.2.faktur_count', 0)
            ->assertJsonPath('months.2.dpp', 0)
            ->assertJsonPath('months.2.ppn', 0)
            ->assertJsonPath('months.2.pph', 0)
            ->assertJsonPath('months.11.month', 12)
            ->assertJsonPath('months.11.orders', 0)
            ->assertJsonPath('months.11.omzet', 0)
            ->assertJsonPath('months.11.pph', 0);
    }

    public function test_tax_report_year_without_orders_returns_zero_filled_months(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/reports/tax?year=2024')
            ->assertOk()
            ->assertJsonPath('year', 2024)
            ->assertJsonPath('pph.mode', 'umkm_non_pkp')
            ->assertJsonPath('pph.total', 0)
            ->assertJsonPath('totals.orders', 0)
            ->assertJsonPath('totals.omzet', 0)
            ->assertJsonPath('totals.dpp', 0)
            ->assertJsonPath('totals.ppn', 0)
            ->assertJsonPath('totals.faktur_count', 0)
            ->assertJsonCount(12, 'months')
            ->assertJsonPath('months.0.month', 1)
            ->assertJsonPath('months.0.omzet', 0)
            ->assertJsonPath('months.0.pph', 0)
            ->assertJsonPath('months.11.month', 12)
            ->assertJsonPath('months.11.omzet', 0)
            ->assertJsonPath('months.11.pph', 0);
    }

    public function test_tax_report_umkm_pkp_22_mode_applies_flat_monthly_rate(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->create();
        Order::factory()->create(['user_id' => $staff->id, 'order_date' => '2026-02-10', 'status' => OrderStatus::Selesai, 'grand_total' => 2000000, 'ppn_amount' => 220000]);
        TenantSettings::for($owner->tenant_id)->set('pajak.pph_mode', 'umkm_pkp_22');
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/reports/tax?year=2026')
            ->assertOk()
            ->assertJsonPath('pph.mode', 'umkm_pkp_22')
            ->assertJsonPath('pph.total', 50000)
            ->assertJsonPath('months.0.pph', 0)
            ->assertJsonPath('months.1.pph', 50000)
            ->assertJsonPath('months.1.dpp', 1780000)
            ->assertJsonPath('totals.omzet', 2000000);
    }

    public function test_daily_report_rejects_invalid_date_parameter(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/reports/daily?date=not-a-date')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date');
    }

    public function test_tax_report_rejects_out_of_range_year(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/reports/tax?year=1999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('year');

        $this->withToken($token)
            ->getJson('/api/reports/tax?year=3000')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('year');

        $this->withToken($token)
            ->getJson('/api/reports/tax?year=abc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('year');
    }

    public function test_tax_report_defaults_to_current_year_when_year_omitted(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/reports/tax')
            ->assertOk()
            ->assertJsonPath('year', now()->year);
    }

    public function test_daily_trend_and_product_reports_exclude_draft_orders(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->create();
        $product = Product::factory()->create(['nama' => 'Kopi']);
        $unit = $product->units()->first();
        $token = $owner->createToken('test')->plainTextToken;

        $draft = Order::factory()->create([
            'user_id' => $staff->id,
            'order_date' => '2026-07-15',
            'status' => OrderStatus::Draft,
            'subtotal' => 70000,
            'grand_total' => 70000,
        ]);
        $draft->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 70000,
            'quantity' => 1,
            'subtotal' => 70000,
        ]);

        $this->withToken($token)
            ->getJson('/api/reports/daily-trend?from=2026-07-14&to=2026-07-16')
            ->assertOk()
            ->assertJsonPath('items.1.total_orders', 0)
            ->assertJsonPath('items.1.total_revenue', 0)
            ->assertJsonPath('totals.total_revenue', 0);

        $this->withToken($token)
            ->getJson('/api/reports/product?product_id=all&from=2026-07-01&to=2026-07-31')
            ->assertOk()
            ->assertJsonPath('items', [])
            ->assertJsonPath('total_orders', 0)
            ->assertJsonPath('net_revenue', 0);
    }

    public function test_status_report_returns_pipeline_ordered_zero_filled_rows(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/reports/status')
            ->assertOk()
            ->assertJsonPath('period.from', null)
            ->assertJsonPath('period.to', null)
            ->assertJsonCount(6, 'statuses')
            ->assertJsonPath('statuses.0.status', 'draft')
            ->assertJsonPath('statuses.1.status', 'pending')
            ->assertJsonPath('statuses.2.status', 'diproses')
            ->assertJsonPath('statuses.3.status', 'dikirim')
            ->assertJsonPath('statuses.4.status', 'selesai')
            ->assertJsonPath('statuses.5.status', 'cancelled')
            ->assertJsonPath('statuses.0.count', 0)
            ->assertJsonPath('statuses.0.total', 0)
            ->assertJsonPath('statuses.0.unpaid_count', 0)
            ->assertJsonPath('statuses.0.unpaid_amount', 0)
            ->assertJsonPath('statuses.3.count', 0)
            ->assertJsonPath('statuses.3.unpaid_amount', 0)
            ->assertJsonPath('statuses.5.count', 0)
            ->assertJsonPath('totals.orders', 0)
            ->assertJsonPath('totals.active_orders', 0)
            ->assertJsonPath('totals.active_value', 0)
            ->assertJsonPath('totals.piutang_count', 0)
            ->assertJsonPath('totals.piutang_amount', 0);
    }

    public function test_status_report_money_math_and_piutang_totals(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->create();
        $token = $owner->createToken('test')->plainTextToken;

        Order::factory()->create(['user_id' => $staff->id, 'order_date' => '2026-07-14', 'status' => OrderStatus::Draft, 'grand_total' => 40000]); // unpaid: full amount
        Order::factory()->create(['user_id' => $staff->id, 'order_date' => '2026-07-14', 'status' => OrderStatus::Pending, 'grand_total' => 100000]); // unpaid: full amount
        Order::factory()->create(['user_id' => $staff->id, 'order_date' => '2026-07-15', 'status' => OrderStatus::Diproses, 'grand_total' => 20000, 'total_paid' => 25000]); // overpaid: clamps to 0
        Order::factory()->create(['user_id' => $staff->id, 'order_date' => '2026-07-15', 'status' => OrderStatus::Selesai, 'grand_total' => 50000, 'total_paid' => 30000]); // partial paid: exact diff
        Order::factory()->create(['user_id' => $staff->id, 'order_date' => '2026-07-16', 'status' => OrderStatus::Cancelled, 'grand_total' => 99999]); // row present, excluded from piutang

        $this->withToken($token)
            ->getJson('/api/reports/status')
            ->assertOk()
            ->assertJsonPath('statuses.0.status', 'draft')
            ->assertJsonPath('statuses.0.count', 1)
            ->assertJsonPath('statuses.0.total', 40000)
            ->assertJsonPath('statuses.0.unpaid_count', 1)
            ->assertJsonPath('statuses.0.unpaid_amount', 40000)
            ->assertJsonPath('statuses.1.status', 'pending')
            ->assertJsonPath('statuses.1.count', 1)
            ->assertJsonPath('statuses.1.total', 100000)
            ->assertJsonPath('statuses.1.unpaid_count', 1)
            ->assertJsonPath('statuses.1.unpaid_amount', 100000)
            ->assertJsonPath('statuses.2.status', 'diproses')
            ->assertJsonPath('statuses.2.count', 1)
            ->assertJsonPath('statuses.2.total', 20000)
            ->assertJsonPath('statuses.2.unpaid_count', 0)
            ->assertJsonPath('statuses.2.unpaid_amount', 0)
            ->assertJsonPath('statuses.3.status', 'dikirim')
            ->assertJsonPath('statuses.3.count', 0)
            ->assertJsonPath('statuses.4.status', 'selesai')
            ->assertJsonPath('statuses.4.count', 1)
            ->assertJsonPath('statuses.4.total', 50000)
            ->assertJsonPath('statuses.4.unpaid_count', 1)
            ->assertJsonPath('statuses.4.unpaid_amount', 20000)
            ->assertJsonPath('statuses.5.status', 'cancelled')
            ->assertJsonPath('statuses.5.count', 1)
            ->assertJsonPath('statuses.5.total', 99999)
            ->assertJsonPath('statuses.5.unpaid_count', 1)
            ->assertJsonPath('statuses.5.unpaid_amount', 99999)
            ->assertJsonPath('totals.orders', 5)
            ->assertJsonPath('totals.active_orders', 3)
            ->assertJsonPath('totals.active_value', 160000)
            ->assertJsonPath('totals.piutang_count', 3)
            ->assertJsonPath('totals.piutang_amount', 160000);
    }

    public function test_status_report_period_filter_includes_single_day_range(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->create();
        $token = $owner->createToken('test')->plainTextToken;

        Order::factory()->create(['user_id' => $staff->id, 'order_date' => '2026-07-14', 'status' => OrderStatus::Pending, 'grand_total' => 10000]);
        Order::factory()->create(['user_id' => $staff->id, 'order_date' => '2026-07-16', 'status' => OrderStatus::Selesai, 'grand_total' => 30000]);

        $this->withToken($token)
            ->getJson('/api/reports/status?from=2026-07-14&to=2026-07-16')
            ->assertOk()
            ->assertJsonPath('period.from', '2026-07-14')
            ->assertJsonPath('period.to', '2026-07-16')
            ->assertJsonPath('totals.orders', 2)
            ->assertJsonPath('statuses.1.count', 1)
            ->assertJsonPath('statuses.4.count', 1);

        // Single-day range must match order_date at 00:00:00 (whereDate semantics).
        $this->withToken($token)
            ->getJson('/api/reports/status?from=2026-07-16&to=2026-07-16')
            ->assertOk()
            ->assertJsonPath('period.from', '2026-07-16')
            ->assertJsonPath('period.to', '2026-07-16')
            ->assertJsonPath('totals.orders', 1)
            ->assertJsonPath('totals.active_orders', 0)
            ->assertJsonPath('totals.active_value', 0)
            ->assertJsonPath('statuses.1.count', 0)
            ->assertJsonPath('statuses.4.count', 1);

        // Both-or-neither: a lone from/to fails validation.
        $this->withToken($token)
            ->getJson('/api/reports/status?from=2026-07-14')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');

        $this->withToken($token)
            ->getJson('/api/reports/status?to=2026-07-16')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('from');
    }

    private function createCompletedOrderWithReturn(
        User $owner,
        string $orderDate,
        string $returnDate,
        int $quantity,
        int $returnedQuantity,
        int $unitPrice,
    ): Product {
        $staff = User::factory()->create();
        $product = Product::factory()->create(['nama' => 'Kopi']);
        $unit = $product->units()->first();

        $order = Order::factory()->create([
            'user_id' => $staff->id,
            'order_date' => $orderDate,
            'subtotal' => $unitPrice * $quantity,
            'grand_total' => $unitPrice * $quantity,
            'status' => OrderStatus::Selesai,
        ]);
        $orderItem = $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => $unitPrice,
            'quantity' => $quantity,
            'subtotal' => $unitPrice * $quantity,
        ]);

        $return = OrderReturn::create([
            'tenant_id' => $owner->tenant_id,
            'order_id' => $order->id,
            'return_no' => 'RET-'.str_replace('-', '', $returnDate).'-'.$order->id,
            'reason' => 'Barang rusak',
            'refund_amount' => $unitPrice * $returnedQuantity,
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

    public function test_daily_report_nets_refund_on_return_date(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->createCompletedOrderWithReturn(
            $owner, '2026-07-14', '2026-07-15', 2, 1, 50000,
        );

        $this->withToken($token)
            ->getJson('/api/reports/daily?date=2026-07-15')
            ->assertOk()
            ->assertJsonPath('total_revenue', 0)
            ->assertJsonPath('returns_total', 50000)
            ->assertJsonPath('net_revenue', -50000);

        $this->withToken($token)
            ->getJson('/api/reports/daily?date=2026-07-14')
            ->assertOk()
            ->assertJsonPath('total_revenue', 100000)
            ->assertJsonPath('returns_total', 0)
            ->assertJsonPath('net_revenue', 100000);
    }

    public function test_daily_trend_attributes_refund_to_return_date(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->createCompletedOrderWithReturn(
            $owner, '2026-07-14', '2026-07-15', 2, 1, 50000,
        );

        $this->withToken($token)
            ->getJson('/api/reports/daily-trend?from=2026-07-14&to=2026-07-16')
            ->assertOk()
            ->assertJsonPath('items.0.date', '2026-07-14')
            ->assertJsonPath('items.0.returns_total', 0)
            ->assertJsonPath('items.0.net_revenue', 100000)
            ->assertJsonPath('items.1.date', '2026-07-15')
            ->assertJsonPath('items.1.returns_total', 50000)
            ->assertJsonPath('items.1.net_revenue', -50000)
            ->assertJsonPath('items.2.date', '2026-07-16')
            ->assertJsonPath('items.2.returns_total', 0)
            ->assertJsonPath('items.2.net_revenue', 0)
            ->assertJsonPath('totals.total_revenue', 100000)
            ->assertJsonPath('totals.returns_total', 50000)
            ->assertJsonPath('totals.net_revenue', 50000)
            ->assertJsonPath('totals.aov', 100000);
    }

    public function test_all_products_report_nets_returned_qty_and_revenue(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->createCompletedOrderWithReturn(
            $owner, '2026-07-14', '2026-07-15', 10, 3, 10000,
        );

        $this->withToken($token)
            ->getJson('/api/reports/product?product_id=all&from=2026-07-01&to=2026-07-30')
            ->assertOk()
            ->assertJsonPath('items.0.total_qty', 10)
            ->assertJsonPath('items.0.returned_qty', 3)
            ->assertJsonPath('items.0.net_qty', 7)
            ->assertJsonPath('items.0.returns_total', 30000)
            ->assertJsonPath('items.0.net_revenue', 70000)
            ->assertJsonPath('total_qty', 10)
            ->assertJsonPath('returned_qty', 3)
            ->assertJsonPath('net_qty', 7)
            ->assertJsonPath('returns_total', 30000)
            ->assertJsonPath('net_revenue', 70000);
    }

    public function test_single_product_report_includes_return_breakdown(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $product = $this->createCompletedOrderWithReturn(
            $owner, '2026-07-14', '2026-07-15', 10, 3, 10000,
        );

        $this->withToken($token)
            ->getJson("/api/reports/product?product_id={$product->id}&from=2026-07-01&to=2026-07-30")
            ->assertOk()
            ->assertJsonPath('total_qty', 10)
            ->assertJsonPath('returned_qty', 3)
            ->assertJsonPath('net_qty', 7)
            ->assertJsonPath('returns_total', 30000)
            ->assertJsonPath('total_revenue', 100000)
            ->assertJsonPath('net_revenue', 70000);
    }

    public function test_reports_unchanged_without_returns(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->create();
        $product = Product::factory()->create(['nama' => 'Teh']);
        $token = $owner->createToken('test')->plainTextToken;
        $unit = $product->units()->first();

        $order = Order::factory()->create([
            'user_id' => $staff->id,
            'order_date' => '2026-07-14',
            'subtotal' => 40000,
            'grand_total' => 40000,
            'status' => OrderStatus::Selesai,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 20000,
            'quantity' => 2,
            'subtotal' => 40000,
        ]);

        $this->withToken($token)
            ->getJson('/api/reports/daily?date=2026-07-14')
            ->assertOk()
            ->assertJsonPath('total_revenue', 40000)
            ->assertJsonPath('returns_total', 0)
            ->assertJsonPath('net_revenue', 40000);

        $this->withToken($token)
            ->getJson('/api/reports/product?product_id=all&from=2026-07-01&to=2026-07-30')
            ->assertOk()
            ->assertJsonPath('returns_total', 0)
            ->assertJsonPath('net_revenue', 40000)
            ->assertJsonPath('items.0.returns_total', 0)
            ->assertJsonPath('items.0.net_revenue', 40000);
    }
}
