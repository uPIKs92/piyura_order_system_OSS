<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private string $ownerToken;
    private User $staff;
    private string $staffToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        $this->ownerToken = $this->owner->createToken('test')->plainTextToken;
        $this->staff = User::factory()->create();
        $this->staffToken = $this->staff->createToken('test')->plainTextToken;
    }

    public function test_summary_scopes_by_month_and_year(): void
    {
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'order_date' => '2026-07-10',
            'status' => OrderStatus::Pending,
            'grand_total' => 100000,
        ]);
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'order_date' => '2026-08-15',
            'status' => OrderStatus::Pending,
            'grand_total' => 200000,
        ]);
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'order_date' => '2025-07-20',
            'status' => OrderStatus::Pending,
            'grand_total' => 400000,
        ]);

        $this->withToken($this->ownerToken)
            ->getJson('/api/orders/summary?month=7&year=2026')
            ->assertOk()
            ->assertJsonPath('total_orders', 1)
            ->assertJsonPath('total_revenue', 100000);

        $this->withToken($this->ownerToken)
            ->getJson('/api/orders/summary?month=7')
            ->assertOk()
            ->assertJsonPath('total_orders', 2);

        $this->withToken($this->ownerToken)
            ->getJson('/api/orders/summary')
            ->assertOk()
            ->assertJsonPath('total_orders', 3)
            ->assertJsonPath('total_revenue', 700000);
    }

    public function test_summary_staff_only_sees_own_orders(): void
    {
        Order::factory()->create([
            'user_id' => $this->owner->id,
            'status' => OrderStatus::Selesai,
            'grand_total' => 500000,
            'total_paid' => 500000,
        ]);
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'status' => OrderStatus::Dikirim,
            'grand_total' => 300000,
            'total_paid' => 100000,
        ]);

        $this->withToken($this->staffToken)
            ->getJson('/api/orders/summary')
            ->assertOk()
            ->assertJsonPath('total_orders', 1)
            ->assertJsonPath('status_counts.dikirim', 1)
            ->assertJsonPath('status_counts.selesai', 0)
            ->assertJsonPath('total_revenue', 300000)
            ->assertJsonPath('unpaid_amount', 200000)
            ->assertJsonPath('unpaid_count', 1);
    }

    public function test_summary_owner_sees_all_orders(): void
    {
        Order::factory()->create([
            'user_id' => $this->owner->id,
            'status' => OrderStatus::Selesai,
            'grand_total' => 500000,
            'total_paid' => 500000,
        ]);
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'status' => OrderStatus::Dikirim,
            'grand_total' => 300000,
            'total_paid' => 100000,
        ]);

        $this->withToken($this->ownerToken)
            ->getJson('/api/orders/summary')
            ->assertOk()
            ->assertJsonPath('total_orders', 2)
            ->assertJsonPath('status_counts.selesai', 1)
            ->assertJsonPath('status_counts.dikirim', 1)
            ->assertJsonPath('total_revenue', 800000)
            ->assertJsonPath('unpaid_amount', 200000)
            ->assertJsonPath('unpaid_count', 1);
    }

    public function test_summary_excludes_draft_and_cancelled_from_revenue_and_unpaid(): void
    {
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'status' => OrderStatus::Draft,
            'grand_total' => 100000,
            'total_paid' => 0,
        ]);
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'status' => OrderStatus::Cancelled,
            'grand_total' => 50000,
            'total_paid' => 0,
        ]);
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'status' => OrderStatus::Pending,
            'grand_total' => 70000,
            'total_paid' => 0,
        ]);
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'status' => OrderStatus::Selesai,
            'grand_total' => 80000,
            'total_paid' => 80000,
        ]);
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'status' => OrderStatus::Dikirim,
            'grand_total' => 90000,
            'total_paid' => 40000,
        ]);

        $this->withToken($this->ownerToken)
            ->getJson('/api/orders/summary')
            ->assertOk()
            ->assertJsonPath('total_orders', 5)
            ->assertJsonPath('status_counts.draft', 1)
            ->assertJsonPath('status_counts.cancelled', 1)
            ->assertJsonPath('status_counts.pending', 1)
            ->assertJsonPath('status_counts.selesai', 1)
            ->assertJsonPath('status_counts.dikirim', 1)
            ->assertJsonPath('total_revenue', 240000)
            ->assertJsonPath('unpaid_amount', 120000)
            ->assertJsonPath('unpaid_count', 2);
    }

    public function test_summary_status_counts_zero_fills_absent_statuses(): void
    {
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'status' => OrderStatus::Pending,
            'grand_total' => 123000,
            'total_paid' => 123000,
        ]);

        $this->withToken($this->ownerToken)
            ->getJson('/api/orders/summary')
            ->assertOk()
            ->assertJsonPath('total_orders', 1)
            ->assertJsonPath('status_counts.draft', 0)
            ->assertJsonPath('status_counts.pending', 1)
            ->assertJsonPath('status_counts.diproses', 0)
            ->assertJsonPath('status_counts.dikirim', 0)
            ->assertJsonPath('status_counts.selesai', 0)
            ->assertJsonPath('status_counts.cancelled', 0)
            ->assertJsonPath('total_revenue', 123000)
            ->assertJsonPath('unpaid_amount', 0)
            ->assertJsonPath('unpaid_count', 0);
    }

    public function test_summary_daily_series_zero_fills_every_day_of_selected_month(): void
    {
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'order_date' => '2026-07-10',
            'status' => OrderStatus::Pending,
            'grand_total' => 100000,
        ]);

        $this->withToken($this->ownerToken)
            ->getJson('/api/orders/summary?month=7&year=2026')
            ->assertOk()
            ->assertJsonCount(31, 'daily')
            ->assertJsonPath('daily.0.date', '2026-07-01')
            ->assertJsonPath('daily.0.orders', 0)
            ->assertJsonPath('daily.0.revenue', 0)
            ->assertJsonPath('daily.9.date', '2026-07-10')
            ->assertJsonPath('daily.9.orders', 1)
            ->assertJsonPath('daily.9.revenue', 100000)
            ->assertJsonPath('daily.30.date', '2026-07-31')
            ->assertJsonPath('daily.30.orders', 0)
            ->assertJsonPath('daily.30.revenue', 0);

        $this->withToken($this->ownerToken)
            ->getJson('/api/orders/summary?month=2&year=2028')
            ->assertOk()
            ->assertJsonCount(29, 'daily')
            ->assertJsonPath('daily.28.date', '2028-02-29')
            ->assertJsonPath('daily.28.orders', 0)
            ->assertJsonPath('daily.28.revenue', 0);

        $this->withToken($this->ownerToken)
            ->getJson('/api/orders/summary?month=2&year=2027')
            ->assertOk()
            ->assertJsonCount(28, 'daily');
    }

    public function test_summary_daily_series_counts_all_statuses_but_excludes_draft_and_cancelled_from_revenue(): void
    {
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'order_date' => '2026-07-05',
            'status' => OrderStatus::Draft,
            'grand_total' => 50000,
        ]);
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'order_date' => '2026-07-05',
            'status' => OrderStatus::Cancelled,
            'grand_total' => 30000,
        ]);
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'order_date' => '2026-07-05',
            'status' => OrderStatus::Selesai,
            'grand_total' => 200000,
            'total_paid' => 200000,
        ]);

        $this->withToken($this->ownerToken)
            ->getJson('/api/orders/summary?month=7&year=2026')
            ->assertOk()
            ->assertJsonPath('daily.4.date', '2026-07-05')
            ->assertJsonPath('daily.4.orders', 3)
            ->assertJsonPath('daily.4.revenue', 200000);
    }

    public function test_summary_daily_series_scopes_staff_to_own_orders(): void
    {
        Order::factory()->create([
            'user_id' => $this->owner->id,
            'order_date' => '2026-07-10',
            'status' => OrderStatus::Selesai,
            'grand_total' => 500000,
            'total_paid' => 500000,
        ]);
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'order_date' => '2026-07-10',
            'status' => OrderStatus::Dikirim,
            'grand_total' => 300000,
            'total_paid' => 100000,
        ]);

        $this->withToken($this->staffToken)
            ->getJson('/api/orders/summary?month=7&year=2026')
            ->assertOk()
            ->assertJsonCount(31, 'daily')
            ->assertJsonPath('daily.9.date', '2026-07-10')
            ->assertJsonPath('daily.9.orders', 1)
            ->assertJsonPath('daily.9.revenue', 300000);
    }

    public function test_summary_daily_series_includes_all_users_orders_for_owner(): void
    {
        Order::factory()->create([
            'user_id' => $this->owner->id,
            'order_date' => '2026-07-10',
            'status' => OrderStatus::Selesai,
            'grand_total' => 500000,
            'total_paid' => 500000,
        ]);
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'order_date' => '2026-07-10',
            'status' => OrderStatus::Dikirim,
            'grand_total' => 300000,
            'total_paid' => 100000,
        ]);

        $this->withToken($this->ownerToken)
            ->getJson('/api/orders/summary?month=7&year=2026')
            ->assertOk()
            ->assertJsonPath('daily.9.date', '2026-07-10')
            ->assertJsonPath('daily.9.orders', 2)
            ->assertJsonPath('daily.9.revenue', 800000);
    }

    public function test_summary_daily_series_covers_last_30_days_when_month_absent(): void
    {
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'order_date' => today()->toDateString(),
            'status' => OrderStatus::Pending,
            'grand_total' => 150000,
        ]);
        Order::factory()->create([
            'user_id' => $this->staff->id,
            'order_date' => today()->subDays(40)->toDateString(),
            'status' => OrderStatus::Pending,
            'grand_total' => 999000,
        ]);

        $this->withToken($this->ownerToken)
            ->getJson('/api/orders/summary')
            ->assertOk()
            ->assertJsonCount(30, 'daily')
            ->assertJsonPath('daily.0.date', today()->subDays(29)->toDateString())
            ->assertJsonPath('daily.0.orders', 0)
            ->assertJsonPath('daily.0.revenue', 0)
            ->assertJsonPath('daily.29.date', today()->toDateString())
            ->assertJsonPath('daily.29.orders', 1)
            ->assertJsonPath('daily.29.revenue', 150000)
            ->assertJsonPath('total_orders', 2);
    }
}
