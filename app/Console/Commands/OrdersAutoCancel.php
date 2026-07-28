<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;

class OrdersAutoCancel extends Command
{
    protected $signature = 'orders:auto-cancel';

    protected $description = 'Cancel pending orders older than 7 days';

    public function handle(OrderService $orderService): int
    {
        $orders = Order::where('status', OrderStatus::Pending)
            ->where('created_at', '<', now()->subDays(7))
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            $orderService->update($order, $order->user, [
                'version' => $order->version,
                'status' => OrderStatus::Cancelled->value,
                'reason' => 'Auto-cancel: expired 7 hari',
            ]);
            $count++;
        }

        $this->info("Auto-cancelled {$count} orders.");

        return self::SUCCESS;
    }
}
