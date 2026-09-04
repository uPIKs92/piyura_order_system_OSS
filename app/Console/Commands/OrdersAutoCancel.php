<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\OrderVersionMismatchException;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class OrdersAutoCancel extends Command
{
    protected $signature = 'orders:auto-cancel';

    protected $description = 'Cancel pending orders older than 7 days';

    public function handle(OrderService $orderService): int
    {
        $count = 0;

        Order::where('status', OrderStatus::Pending)
            ->where('created_at', '<', now()->subDays(7))
            ->with('user')
            ->chunkById(200, function ($orders) use ($orderService, &$count) {
                foreach ($orders as $order) {
                    $causer = $order->user ?? $order->tenant?->users()
                        ->where('role', UserRole::Owner)
                        ->first();

                    if (! $causer) {
                        $this->warn("Order #{$order->id}: no valid causer, skipping.");

                        continue;
                    }

                    try {
                        $orderService->update($order, $causer, [
                            'version' => $order->version,
                            'status' => OrderStatus::Cancelled->value,
                            'reason' => 'Auto-cancel: expired 7 hari',
                        ]);
                        $count++;
                    } catch (InvalidStatusTransitionException|OrderVersionMismatchException $e) {
                        Log::warning('Auto-cancel skipped order', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("Auto-cancelled {$count} orders.");

        return self::SUCCESS;
    }
}
