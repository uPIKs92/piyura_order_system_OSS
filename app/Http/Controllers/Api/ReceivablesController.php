<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class ReceivablesController extends Controller
{
    public function index(): JsonResponse
    {
        $orders = Order::query()
            ->whereColumn('grand_total', '>', 'total_paid')
            ->whereNotIn('status', [OrderStatus::Draft, OrderStatus::Cancelled])
            ->orderBy('order_date')
            ->orderBy('id')
            ->get([
                'id', 'user_id', 'customer_name', 'customer_phone',
                'order_date', 'status', 'grand_total', 'total_paid',
            ]);

        // Orders have no customer_id FK — customers match by phone
        // (same rule as Customer::upsertFromOrder), name as fallback.
        $phones = $orders->pluck('customer_phone')->map(fn ($phone) => trim((string) $phone))->filter()->unique();

        $customers = Customer::query()
            ->whereIn('phone', $phones)
            ->get()
            ->keyBy('phone');

        $groups = [];

        foreach ($orders as $order) {
            $phone = trim((string) $order->customer_phone);
            $key = $phone !== ''
                ? 'p:'.preg_replace('/[^0-9+]/', '', $phone)
                : 'n:'.$this->normalizedName($order->customer_name);

            if (! isset($groups[$key])) {
                $customer = $phone !== '' ? $customers->get($phone) : null;

                $groups[$key] = [
                    'customer_id' => $customer?->id,
                    'name' => $customer?->name ?? trim((string) $order->customer_name) ?: 'Tanpa Nama',
                    'phone' => $phone !== '' ? $phone : null,
                    'unpaid_amount' => 0.0,
                    'unpaid_count' => 0,
                    'oldest_order_date' => null,
                    'orders' => [],
                ];
            }

            $due = (float) $order->grand_total - (float) $order->total_paid;

            $groups[$key]['unpaid_amount'] += $due;
            $groups[$key]['unpaid_count']++;
            $groups[$key]['orders'][] = [
                'id' => $order->id,
                'order_date' => Carbon::parse($order->order_date)->toDateString(),
                'grand_total' => (float) $order->grand_total,
                'total_paid' => (float) $order->total_paid,
                'due_amount' => round($due, 2),
                'status' => $order->status->value,
                'user_id' => (int) $order->user_id,
            ];
        }

        $items = collect($groups)
            ->map(function (array $group) {
                $oldest = Carbon::parse(min(array_column($group['orders'], 'order_date')));
                $oldestDays = max(0, (int) $oldest->startOfDay()->diffInDays(today()));

                $group['oldest_order_date'] = $oldest->toDateString();
                $group['oldest_days'] = $oldestDays;
                $group['aging_bucket'] = $this->agingBucket($oldestDays);
                $group['unpaid_amount'] = round($group['unpaid_amount'], 2);

                return $group;
            })
            ->sortBy([
                ['unpaid_amount', 'desc'],
                ['oldest_order_date', 'asc'],
                ['name', 'asc'],
            ])
            ->values()
            ->all();

        return response()->json([
            'totals' => [
                'unpaid_amount' => array_sum(array_column($items, 'unpaid_amount')),
                'unpaid_count' => array_sum(array_column($items, 'unpaid_count')),
                'debtor_count' => count($items),
            ],
            'groups' => $items,
        ]);
    }

    private function normalizedName(?string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $name)));
    }

    private function agingBucket(int $days): string
    {
        return match (true) {
            $days <= 7 => '<=7',
            $days <= 14 => '8-14',
            $days <= 30 => '15-30',
            default => '>30',
        };
    }
}
