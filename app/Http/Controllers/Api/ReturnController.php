<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\ReturnItem;
use App\Services\StockMovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReturnController extends Controller
{
    public function __construct(private StockMovementService $stockMovement) {}

    public function store(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);
        abort_unless($request->user()->isOwner(), 403);
        abort_unless($order->status === OrderStatus::Selesai, 422, 'Returns only allowed on completed orders.');

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1|max:50',
            'items.*.order_item_id' => 'required|integer|distinct|exists:order_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.reason' => 'nullable|string|max:500',
        ]);

        $itemIds = collect($validated['items'])->pluck('order_item_id');
        if ($itemIds->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => ['Duplicate return items are not allowed.'],
            ]);
        }

        return DB::transaction(function () use ($request, $order, $validated) {
            $refundAmount = 0;

            $orderReturn = OrderReturn::create([
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'return_no' => 'RET-'.now()->format('Ymd').'-'.Str::upper(Str::random(4)),
                'reason' => $validated['reason'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $orderItemIds = collect($validated['items'])->pluck('order_item_id')->all();
            $orderItems = OrderItem::query()
                ->where('order_id', $order->id)
                ->whereIn('id', $orderItemIds)
                ->lockForUpdate()
                ->with('productUnit')
                ->get()
                ->keyBy('id');

            $returnedQuantities = ReturnItem::query()
                ->whereIn('order_item_id', $orderItemIds)
                ->selectRaw('order_item_id, SUM(quantity) as returned_qty')
                ->groupBy('order_item_id')
                ->pluck('returned_qty', 'order_item_id');

            foreach ($validated['items'] as $itemData) {
                $orderItem = $orderItems->get($itemData['order_item_id']);
                abort_unless($orderItem, 404);

                $alreadyReturned = (int) ($returnedQuantities[$orderItem->id] ?? 0);
                $requested = (int) $itemData['quantity'];

                if ($alreadyReturned + $requested > $orderItem->quantity) {
                    throw ValidationException::withMessages([
                        'items' => ['Return quantity exceeds remaining order quantity.'],
                    ]);
                }

                ReturnItem::create([
                    'return_id' => $orderReturn->id,
                    'order_item_id' => $orderItem->id,
                    'quantity' => $requested,
                    'reason' => $itemData['reason'] ?? null,
                    'created_at' => now(),
                ]);

                $refundAmount += (float) $orderItem->price_snapshot * $requested;

                if ($orderItem->productUnit) {
                    $this->stockMovement->increment(
                        $orderItem->productUnit,
                        $requested,
                        StockMovementType::Return,
                        $request->user(),
                        null,
                        $orderReturn,
                    );
                }
            }

            $orderReturn->update(['refund_amount' => $refundAmount, 'restock_status' => true]);

            return response()->json($orderReturn->load('items'), 201);
        });
    }
}
