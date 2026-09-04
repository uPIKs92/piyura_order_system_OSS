<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExpenseTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
        $this->token = $this->owner->createToken('test')->plainTextToken;
    }

    private function createOrderWithItem(User $staff, string $date, int $quantity, int $unitPrice = 30000, int $unitCost = 10000): Order
    {
        $product = Product::factory()->create();
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

    public function test_daily_report_includes_expenses_total_and_laba_bersih(): void
    {
        $staff = User::factory()->create();
        $this->createOrderWithItem($staff, '2026-07-15', 1);
        Expense::factory()->create([
            'expense_date' => '2026-07-15',
            'amount' => 5000,
            'category' => 'transport',
        ]);
        Expense::factory()->create([
            'expense_date' => '2026-07-15',
            'amount' => 2500.50,
            'category' => 'bahan_baku',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/reports/daily?date=2026-07-15')
            ->assertOk()
            ->assertJsonPath('net_profit', 20000)
            ->assertJsonPath('expenses_total', 7500.5)
            ->assertJsonPath('laba_bersih', 12499.5);
    }

    public function test_daily_report_zero_expenses_when_none_recorded(): void
    {
        $staff = User::factory()->create();
        $this->createOrderWithItem($staff, '2026-07-15', 1);

        $this->withToken($this->token)
            ->getJson('/api/reports/daily?date=2026-07-15')
            ->assertOk()
            ->assertJsonPath('net_profit', 20000)
            ->assertJsonPath('expenses_total', 0)
            ->assertJsonPath('laba_bersih', 20000);
    }

    public function test_daily_trend_includes_expenses_per_day_and_totals(): void
    {
        $staff = User::factory()->create();
        $this->createOrderWithItem($staff, '2026-07-14', 1); // turnover 30000, cost 10000
        $this->createOrderWithItem($staff, '2026-07-16', 2); // turnover 60000, cost 20000
        Expense::factory()->create(['expense_date' => '2026-07-14', 'amount' => 5000]);
        Expense::factory()->create(['expense_date' => '2026-07-15', 'amount' => 8000]);

        $this->withToken($this->token)
            ->getJson('/api/reports/daily-trend?from=2026-07-14&to=2026-07-16')
            ->assertOk()
            ->assertJsonPath('items.0.date', '2026-07-14')
            ->assertJsonPath('items.0.sales_turnover', 30000)
            ->assertJsonPath('items.0.purchasing_cost', 10000)
            ->assertJsonPath('items.0.net_profit', 20000)
            ->assertJsonPath('items.0.expenses_total', 5000)
            ->assertJsonPath('items.0.laba_bersih', 15000)
            ->assertJsonPath('items.1.date', '2026-07-15')
            ->assertJsonPath('items.1.net_profit', 0)
            ->assertJsonPath('items.1.expenses_total', 8000)
            ->assertJsonPath('items.1.laba_bersih', -8000)
            ->assertJsonPath('items.2.date', '2026-07-16')
            ->assertJsonPath('items.2.net_profit', 40000)
            ->assertJsonPath('items.2.expenses_total', 0)
            ->assertJsonPath('items.2.laba_bersih', 40000)
            ->assertJsonPath('totals.sales_turnover', 90000)
            ->assertJsonPath('totals.purchasing_cost', 30000)
            ->assertJsonPath('totals.net_profit', 60000)
            ->assertJsonPath('totals.expenses_total', 13000)
            ->assertJsonPath('totals.laba_bersih', 47000);
    }

    public function test_expenses_outside_period_do_not_leak_into_trend(): void
    {
        $staff = User::factory()->create();
        $this->createOrderWithItem($staff, '2026-07-15', 1);
        Expense::factory()->create(['expense_date' => '2026-07-10', 'amount' => 100000]);
        Expense::factory()->create(['expense_date' => '2026-07-20', 'amount' => 100000]);

        $this->withToken($this->token)
            ->getJson('/api/reports/daily-trend?from=2026-07-14&to=2026-07-16')
            ->assertOk()
            ->assertJsonPath('items.1.expenses_total', 0)
            ->assertJsonPath('items.1.laba_bersih', 20000)
            ->assertJsonPath('totals.expenses_total', 0)
            ->assertJsonPath('totals.laba_bersih', 20000);
    }

    public function test_daily_report_reflects_newly_recorded_expense_without_stale_cache(): void
    {
        $staff = User::factory()->create();
        $this->createOrderWithItem($staff, '2026-07-15', 1);

        $this->withToken($this->token)
            ->getJson('/api/reports/daily?date=2026-07-15')
            ->assertOk()
            ->assertJsonPath('expenses_total', 0);

        $this->withToken($this->token)
            ->postJson('/api/expenses', [
                'expense_date' => '2026-07-15',
                'amount' => 12000,
                'category' => 'operasional',
            ])
            ->assertCreated();

        $this->withToken($this->token)
            ->getJson('/api/reports/daily?date=2026-07-15')
            ->assertOk()
            ->assertJsonPath('expenses_total', 12000)
            ->assertJsonPath('laba_bersih', 8000);
    }

    public function test_other_tenant_expenses_are_excluded_from_reports(): void
    {
        $staff = User::factory()->create();
        $this->createOrderWithItem($staff, '2026-07-15', 1);
        $tenantB = Tenant::factory()->create(['slug' => 'toko-b']);
        Expense::factory()->create([
            'tenant_id' => $tenantB->id,
            'expense_date' => '2026-07-15',
            'amount' => 99000,
        ]);
        Expense::factory()->create(['expense_date' => '2026-07-15', 'amount' => 4000]);

        $this->withToken($this->token)
            ->getJson('/api/reports/daily?date=2026-07-15')
            ->assertOk()
            ->assertJsonPath('expenses_total', 4000)
            ->assertJsonPath('laba_bersih', 16000);
    }

    public function test_draft_orders_do_not_count_toward_net_profit_but_expenses_still_do(): void
    {
        $staff = User::factory()->create();
        $draft = Order::factory()->create([
            'user_id' => $staff->id,
            'order_date' => '2026-07-15',
            'status' => OrderStatus::Draft,
            'subtotal' => 50000,
            'grand_total' => 50000,
        ]);
        $product = Product::factory()->create();
        $unit = $product->units()->first();
        $draft->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 50000,
            'cost_snapshot' => 20000,
            'quantity' => 1,
            'subtotal' => 50000,
        ]);
        Expense::factory()->create(['expense_date' => '2026-07-15', 'amount' => 6000]);

        $this->withToken($this->token)
            ->getJson('/api/reports/daily?date=2026-07-15')
            ->assertOk()
            ->assertJsonPath('net_profit', 0)
            ->assertJsonPath('expenses_total', 6000)
            ->assertJsonPath('laba_bersih', -6000);
    }
}
