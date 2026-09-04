<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AggregateCacheTest extends TestCase
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

    private function createOrder(array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'user_id' => $this->owner->id,
            'status' => OrderStatus::Pending,
            'grand_total' => 100000,
            'total_paid' => 100000,
        ], $attributes));
    }

    private function createOrderViaApi(?string $orderDate = null): Order
    {
        $product = Product::factory()->create();
        $product->defaultUnit()->update(['stok' => 100]);
        $unit = $product->defaultUnit();

        $payload = [
            'items' => [['product_unit_id' => $unit->id, 'quantity' => 1]],
        ];

        if ($orderDate !== null) {
            $payload['order_date'] = $orderDate;
        }

        $this->withToken($this->token)->postJson('/api/orders', $payload)->assertCreated();

        $order = Order::query()->latest('id')->firstOrFail();
        // transitionStatus mutates in-memory state only; the caller persists
        // (mirroring OrderService::update's save-then-return contract).
        app(OrderService::class)->transitionStatus($order, OrderStatus::Pending, $this->owner);
        $order->save();

        return $order->fresh();
    }

    private function queryCount(callable $request): int
    {
        DB::flushQueryLog();
        $request();
        $count = count(DB::getQueryLog());

        return $count;
    }

    public function test_order_summary_is_cached_and_invalidated_by_mutations(): void
    {
        $this->createOrder();

        DB::enableQueryLog();
        $fillQueries = $this->queryCount(fn () => $this
            ->withToken($this->token)
            ->getJson('/api/orders/summary')
            ->assertOk()
            ->assertJsonPath('total_orders', 1));

        $hitQueries = $this->queryCount(fn () => $this
            ->withToken($this->token)
            ->getJson('/api/orders/summary')
            ->assertOk()
            ->assertJsonPath('total_orders', 1));

        $this->assertLessThan($fillQueries, $hitQueries, 'Cached summary should skip aggregate queries');

        $this->createOrderViaApi();

        $this->withToken($this->token)
            ->getJson('/api/orders/summary')
            ->assertOk()
            ->assertJsonPath('total_orders', 2);

        DB::disableQueryLog();
    }

    public function test_daily_report_is_cached_and_invalidated_by_mutations(): void
    {
        $today = now()->toDateString();
        $this->createOrder(['order_date' => $today]);

        DB::enableQueryLog();
        $fillQueries = $this->queryCount(fn () => $this
            ->withToken($this->token)
            ->getJson('/api/reports/daily')
            ->assertOk()
            ->assertJsonPath('total_orders', 1));

        $hitQueries = $this->queryCount(fn () => $this
            ->withToken($this->token)
            ->getJson('/api/reports/daily')
            ->assertOk()
            ->assertJsonPath('total_orders', 1));

        $this->assertLessThan($fillQueries, $hitQueries, 'Cached report should skip aggregate queries');

        $this->createOrderViaApi($today);

        $this->withToken($this->token)
            ->getJson('/api/reports/daily')
            ->assertOk()
            ->assertJsonPath('total_orders', 2);

        DB::disableQueryLog();
    }

    public function test_payment_updates_reach_cached_summary_unpaid_totals(): void
    {
        $order = $this->createOrder(['total_paid' => 0]);

        $this->withToken($this->token)
            ->getJson('/api/orders/summary')
            ->assertOk()
            ->assertJsonPath('unpaid_count', 1)
            ->assertJsonPath('unpaid_amount', 100000);

        app(PaymentService::class)->addPayment($order, $this->owner, [
            'amount' => 100000,
            'metode' => 'cash',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/orders/summary')
            ->assertOk()
            ->assertJsonPath('unpaid_count', 0)
            ->assertJsonPath('unpaid_amount', 0);
    }

    public function test_cached_daily_report_keeps_by_status_map_on_cache_hits(): void
    {
        $today = now()->toDateString();
        $this->createOrder(['order_date' => $today]);

        // Fresh compute: by_status is a status => count map.
        $this->withToken($this->token)
            ->getJson('/api/reports/daily')
            ->assertOk()
            ->assertJsonPath('by_status.pending', 1);

        // Cache hit: cache stores unserialize with objects disabled, so an
        // embedded Collection would degrade into __PHP_Incomplete_Class
        // debris. The payload must be scalar/array end to end.
        $this->withToken($this->token)
            ->getJson('/api/reports/daily')
            ->assertOk()
            ->assertJsonPath('by_status.pending', 1);
    }
}
