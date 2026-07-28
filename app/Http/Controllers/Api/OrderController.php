<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\OrderVersionMismatchException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService)
    {
        $this->authorizeResource(Order::class, 'order');
    }

    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['items', 'user'])->latest();

        if ($request->user()->isStaff()) {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_address' => 'nullable|string',
            'order_date' => 'nullable|date|before_or_equal:today|after_or_equal:2020-01-01',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.product_unit_id' => 'required_with:items|exists:product_units,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.discount_type' => 'nullable|in:percent,fixed',
            'items.*.discount_value' => 'nullable|numeric|min:0',
        ]);

        $order = $this->orderService->create($request->user(), $validated);

        return response()->json($order, 201);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json($order->load(['items.product', 'items.productUnit', 'payments', 'statusLogs.changedBy', 'dateHistories.changedBy', 'user']));
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'version' => 'required|integer',
            'status' => 'sometimes|in:draft,pending,diproses,dikirim,selesai,cancelled',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_address' => 'nullable|string',
            'order_date' => 'sometimes|date|before_or_equal:today|after_or_equal:2020-01-01',
            'date_change_reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'reason' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.product_unit_id' => 'required_with:items|exists:product_units,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.discount_type' => 'nullable|in:percent,fixed',
            'items.*.discount_value' => 'nullable|numeric|min:0',
        ]);

        try {
            $order = $this->orderService->update($order, $request->user(), $validated);
        } catch (OrderVersionMismatchException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (InvalidStatusTransitionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (InsufficientStockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($order);
    }

    public function destroy(Request $request, Order $order): JsonResponse
    {
        if ($request->boolean('force')) {
            $this->authorize('forceDelete', $order);
            $this->orderService->forceDelete($order, $request->user());

            return response()->json(['message' => 'Order permanently deleted.']);
        }

        $this->orderService->softDelete($order, $request->user());

        return response()->json(['message' => 'Order deleted.']);
    }

    public function reassign(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);
        abort_unless($request->user()->isOwner(), 403);

        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'from_user_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'to_user_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
        ]);

        $count = Order::where('user_id', $validated['from_user_id'])
            ->update(['user_id' => $validated['to_user_id']]);

        activity()->causedBy($request->user())->log("Reassigned {$count} orders from user {$validated['from_user_id']} to {$validated['to_user_id']}");

        return response()->json(['message' => "{$count} orders reassigned.", 'count' => $count]);
    }
}
