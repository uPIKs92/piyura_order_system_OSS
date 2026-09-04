<?php

namespace Tests\Feature;

use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\OrderVersionMismatchException;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use App\Services\BatchService;
use App\Services\OrderService;
use App\Services\SheetsSyncService;
use App\Services\StockMovementService;
use App\Enums\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class OrdersAutoCancelTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_orders_older_than_7_days_are_cancelled(): void
    {
        $staff = User::factory()->create();
        $product = Product::factory()->create();
        $unit = $product->units()->first();
        $unit->update(['stok' => 20]);
        $order = Order::factory()->pending()->create([
            'user_id' => $staff->id,
            'created_at' => now()->subDays(8),
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 1000,
            'quantity' => 5,
            'subtotal' => 5000,
        ]);
        $unit->update(['stok' => 15]);

        $this->artisan('orders:auto-cancel')->assertSuccessful();

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertEquals(20, $unit->fresh()->stok);
        $this->assertDatabaseHas('order_status_logs', [
            'order_id' => $order->id,
            'reason' => 'Auto-cancel: expired 7 hari',
        ]);
    }

    public function test_invalid_transition_on_one_order_does_not_abort_run(): void
    {
        $this->assertPoisonOrderDoesNotAbortRun(
            new InvalidStatusTransitionException('pending', 'cancelled')
        );
    }

    public function test_version_mismatch_on_one_order_does_not_abort_run(): void
    {
        $this->assertPoisonOrderDoesNotAbortRun(
            new OrderVersionMismatchException
        );
    }

    private function assertPoisonOrderDoesNotAbortRun(\Exception $exception): void
    {
        $staff = User::factory()->create();
        $product = Product::factory()->create();
        $unit = $product->units()->first();
        $unit->update(['stok' => 20]);

        $poison = $this->stalePendingOrder($staff, $product, $unit);
        $good = $this->stalePendingOrder($staff, $product, $unit);

        $real = $this->app->make(OrderService::class);

        $mock = Mockery::mock(OrderService::class, [
            $this->app->make(SheetsSyncService::class),
            $this->app->make(StockMovementService::class),
            $this->app->make(BatchService::class),
        ])->makePartial();

        $mock->shouldReceive('update')->andReturnUsing(
            function (Order $order, User $user, array $data) use ($real, $poison, $exception) {
                if ($order->id === $poison->id) {
                    throw $exception;
                }

                return $real->update($order, $user, $data);
            }
        );

        $this->instance(OrderService::class, $mock);

        $this->artisan('orders:auto-cancel')->assertSuccessful();

        $this->assertSame(OrderStatus::Pending, $poison->fresh()->status);
        $this->assertSame(OrderStatus::Cancelled, $good->fresh()->status);
        $this->assertDatabaseHas('order_status_logs', [
            'order_id' => $good->id,
            'reason' => 'Auto-cancel: expired 7 hari',
        ]);
    }

    private function stalePendingOrder(User $staff, Product $product, ProductUnit $unit): Order
    {
        $order = Order::factory()->pending()->create([
            'user_id' => $staff->id,
            'created_at' => now()->subDays(8),
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 1000,
            'quantity' => 5,
            'subtotal' => 5000,
        ]);

        return $order;
    }
}
