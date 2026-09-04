<?php

namespace App\Http\Controllers\Api;

use Closure;
use App\Exceptions\ExpiredStockException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\OrderLockedException;
use App\Exceptions\OrderVersionMismatchException;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use App\Support\AppTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService)
    {
        $this->authorizeResource(Order::class, 'order');
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => 'sometimes|integer|min:1|max:12',
            'year' => 'sometimes|integer|min:2020|max:2100',
            'sort' => 'sometimes|string|in:date_desc,date_asc,amount_desc,amount_asc,invoice_desc,invoice_asc',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'status' => ['sometimes', 'nullable', 'string', Rule::in(array_column(OrderStatus::cases(), 'value'))],
        ]);

        $query = Order::select([
            'id', 'tenant_id', 'user_id', 'invoice_no', 'status',
            'customer_name', 'order_date', 'grand_total', 'total_paid',
            'version', 'created_at', 'updated_at', 'deleted_at',
        ])->with('items:id,order_id,product_name,satuan,quantity');

        if ($request->user()->isStaff()) {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $validated['status']);
        }

        if ($request->filled('search')) {
            $search = addcslashes(mb_substr((string) $request->search, 0, 100), '%_\\');
            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('month')) {
            $query->whereMonth('order_date', (int) $validated['month']);
        }

        if ($request->filled('year')) {
            $query->whereYear('order_date', (int) $validated['year']);
        }

        $sort = $validated['sort'] ?? 'date_desc';
        match ($sort) {
            'date_asc' => $query->orderBy('order_date')->orderBy('id'),
            'amount_desc' => $query->orderByDesc('grand_total')->orderByDesc('id'),
            'amount_asc' => $query->orderBy('grand_total')->orderBy('id'),
            'invoice_desc' => $query->orderByDesc('invoice_no')->orderByDesc('id'),
            'invoice_asc' => $query->orderBy('invoice_no')->orderBy('id'),
            default => $query->orderByDesc('order_date')->orderByDesc('id'),
        };

        return response()->json($query->paginate((int) ($validated['per_page'] ?? 20)));
    }

    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => 'sometimes|integer|min:1|max:12',
            'year' => 'sometimes|integer|min:2020|max:2100',
        ]);

        $this->authorize('viewAny', Order::class);

        $baseQuery = Order::query();

        if ($request->user()->isStaff()) {
            $baseQuery->where('user_id', $request->user()->id);
        }

        if ($request->filled('month')) {
            $baseQuery->whereMonth('order_date', (int) $validated['month']);
        }

        if ($request->filled('year')) {
            $baseQuery->whereYear('order_date', (int) $validated['year']);
        }

        // Scope + window fold into the suffix; the tenant and aggregate
        // version come from rememberAggregate. today() matters because the
        // default series window ends at today's end-of-day.
        $scope = $request->user()->isStaff() ? 'u'.$request->user()->id : 'all';
        $suffix = sprintf(
            'summary.%s.m%d.y%d.%s',
            $scope,
            (int) ($validated['month'] ?? 0),
            (int) ($validated['year'] ?? 0),
            AppTime::today()->toDateString(),
        );

        $payload = OrderService::rememberAggregate($suffix, function () use ($baseQuery, $validated, $request) {
            $statusCounts = array_fill_keys(['draft', 'pending', 'diproses', 'dikirim', 'selesai', 'cancelled'], 0);

            $grouped = (clone $baseQuery)->toBase()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            foreach ($grouped as $status => $count) {
                $statusCounts[(string) $status] = (int) $count;
            }

            $billed = (clone $baseQuery)
                ->whereNotIn('status', [OrderStatus::Draft, OrderStatus::Cancelled]);

            $totalRevenue = (clone $billed)->sum('grand_total');

            $unpaid = (clone $billed)
                ->whereColumn('grand_total', '>', 'total_paid')
                ->toBase()
                ->selectRaw('COALESCE(SUM(grand_total - total_paid), 0) as unpaid_amount')
                ->selectRaw('COUNT(*) as unpaid_count')
                ->first();

            if ($request->filled('month')) {
                $seriesStart = AppTime::now()->setDate((int) ($validated['year'] ?? AppTime::now()->year), (int) $validated['month'], 1)->startOfDay();
                $seriesEnd = (clone $seriesStart)->endOfMonth();
            } else {
                $seriesStart = AppTime::today()->subDays(29)->startOfDay();
                $seriesEnd = AppTime::today()->endOfDay();
            }

            $dailyOrderCounts = (clone $baseQuery)->toBase()
                ->whereBetween('order_date', [$seriesStart, $seriesEnd])
                ->selectRaw('DATE(order_date) as day, count(*) as aggregate')
                ->groupBy('day')
                ->pluck('aggregate', 'day');

            $dailyRevenueTotals = (clone $baseQuery)->toBase()
                ->whereBetween('order_date', [$seriesStart, $seriesEnd])
                ->whereNotIn('status', [OrderStatus::Draft, OrderStatus::Cancelled])
                ->selectRaw('DATE(order_date) as day, COALESCE(SUM(grand_total), 0) as aggregate')
                ->groupBy('day')
                ->pluck('aggregate', 'day');

            $daily = [];
            foreach ($seriesStart->toPeriod($seriesEnd) as $day) {
                $date = $day->toDateString();
                $daily[] = [
                    'date' => $date,
                    'orders' => (int) ($dailyOrderCounts[$date] ?? 0),
                    'revenue' => (float) ($dailyRevenueTotals[$date] ?? 0),
                ];
            }

            return [
                'total_orders' => (clone $baseQuery)->count(),
                'status_counts' => $statusCounts,
                'total_revenue' => (float) $totalRevenue,
                'unpaid_amount' => (float) $unpaid->unpaid_amount,
                'unpaid_count' => (int) $unpaid->unpaid_count,
                'daily' => $daily,
            ];
        });

        return response()->json($payload);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_address' => 'nullable|string',
            'delivery_method' => 'nullable|in:diantar,diambil',
            'delivery_distance_km' => 'nullable|numeric|between:0,999',
            'delivery_fee' => 'nullable|numeric|min:0',
            'order_date' => 'nullable|date|before_or_equal:today|after_or_equal:2020-01-01',
            'notes' => 'nullable|string',
            'items' => 'nullable|array|max:100',
            'items.*.product_unit_id' => 'required_with:items|exists:product_units,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.discount_type' => 'nullable|in:percent,fixed',
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0', $this->percentDiscountLimit($request)],
        ]);

        $order = $this->orderService->create($request->user(), $validated);

        if ($request->user()->isStaff()) {
            $this->hideItemCosts($order);
        }

        return response()->json($order, 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $order->load(['items.product', 'items.productUnit', 'payments', 'statusLogs.changedBy', 'dateHistories.changedBy', 'user']);

        if ($request->user()->isStaff()) {
            $this->hideItemCosts($order);
        }

        return response()->json($order);
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'version' => 'required|integer',
            'status' => 'sometimes|in:draft,pending,diproses,dikirim,selesai,cancelled',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_address' => 'nullable|string',
            'delivery_method' => 'nullable|in:diantar,diambil',
            'delivery_distance_km' => 'nullable|numeric|between:0,999',
            'delivery_fee' => 'nullable|numeric|min:0',
            'order_date' => 'sometimes|date|before_or_equal:today|after_or_equal:2020-01-01',
            'date_change_reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'reason' => 'nullable|string',
            'items' => 'nullable|array|max:100',
            'items.*.product_unit_id' => 'required_with:items|exists:product_units,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.discount_type' => 'nullable|in:percent,fixed',
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0', $this->percentDiscountLimit($request)],
        ]);

        try {
            $order = $this->orderService->update($order, $request->user(), $validated);
        } catch (OrderVersionMismatchException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (InvalidStatusTransitionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (OrderLockedException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (InsufficientStockException|ExpiredStockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($request->user()->isStaff()) {
            $this->hideItemCosts($order);
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

    private function hideItemCosts(Order $order): void
    {
        $order->items->each(function (OrderItem $item) {
            $item->makeHidden('cost_snapshot');

            if ($item->relationLoaded('productUnit') && $item->productUnit !== null) {
                $item->productUnit->makeHidden('harga_beli');
            }
        });
    }

    private function percentDiscountLimit(Request $request): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($request) {
            $index = (int) explode('.', $attribute)[1];

            if ($request->input("items.{$index}.discount_type") === 'percent' && (float) $value > 100) {
                $fail('Diskon persen tidak boleh lebih dari 100.');
            }
        };
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
