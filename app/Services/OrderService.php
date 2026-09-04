<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\OrderLockedException;
use App\Exceptions\OrderVersionMismatchException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDateHistory;
use App\Models\OrderItem;
use App\Models\OrderItemBatch;
use App\Models\OrderStatusLog;
use App\Models\ProductUnit;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AppTime;
use App\Support\PpnSettings;
use App\Support\TenantProductUnitGuard;
use App\Support\TenantSettings;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OrderService
{
    private const AGGREGATES_VERSION_KEY = 'orders.aggregates_version.';

    public function __construct(
        private SheetsSyncService $sheetsSync,
        private StockMovementService $stockMovement,
        private BatchService $batches,
    ) {}

    /**
     * Per-tenant counter backing the aggregate caches (order summary,
     * reports). The read side keys its Cache::remember entries on this
     * value; every order mutation bumps it, which abandons the previous
     * keys — plain string keys only, so file/database drivers work too.
     */
    public static function aggregatesVersion(int $tenantId): int
    {
        return (int) Cache::get(self::AGGREGATES_VERSION_KEY.$tenantId, 0);
    }

    public static function bumpAggregatesVersion(int $tenantId): void
    {
        Cache::forever(self::AGGREGATES_VERSION_KEY.$tenantId, self::aggregatesVersion($tenantId) + 1);
    }

    /**
     * Remember a tenant-scoped aggregate payload (order summary, report
     * sections) keyed on the aggregates version, so any order/payment/
     * return mutation invalidates it. TTL only reaps abandoned keys of
     * previous versions; correctness comes from the version bump.
     *
     * The computed payload is normalized through a JSON round-trip: the
     * cache stores are configured with serializable_classes = false, so any
     * embedded Collection/Carbon would come back from a cache hit as
     * __PHP_Incomplete_Class garbage. Round-tripping guarantees the fresh
     * (uncached) response and the cached response have the exact same
     * scalar/array shape.
     */
    public static function rememberAggregate(string $suffix, \Closure $compute): array
    {
        $tenantId = app()->bound('currentTenantId') ? (int) app('currentTenantId') : null;

        if ($tenantId === null) {
            return $compute();
        }

        return Cache::remember(
            sprintf('agg.%s.%d.v%d', $suffix, $tenantId, self::aggregatesVersion($tenantId)),
            now()->addHours(6),
            function () use ($compute): array {
                /** @var mixed $payload */
                $payload = $compute();

                return json_decode(
                    json_encode($payload, JSON_THROW_ON_ERROR),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
            },
        );
    }

    public function generateInvoiceNo(int $tenantId): string
    {
        $date = AppTime::now()->format('Ymd');
        $prefix = "INV-{$date}-";
        $last = Order::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('invoice_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('invoice_no');

        $seq = $last ? ((int) substr($last, -3)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    public function create(User $user, array $data): Order
    {
        $order = DB::transaction(function () use ($user, $data) {
            $order = $this->insertOrderWithUniqueInvoiceNo($user, $data);

            if (! empty($data['items'])) {
                $this->syncItems($order, $data['items']);
            }

            $this->recalculateTotals($order);

            Customer::upsertFromOrder($order);

            return $order->fresh(['items', 'user']);
        });

        self::bumpAggregatesVersion($user->tenant_id);

        return $order;
    }

    /**
     * Insert the order row, retrying with the next invoice number when a
     * concurrent create grabbed the same number. A duplicate-key failure is
     * statement-scoped, so the surrounding transaction stays usable and only
     * the invoice number is regenerated between attempts. The next candidate
     * is derived by incrementing the attempted sequence (not by re-reading
     * max), because under REPEATABLE READ the retry's SELECT would still see
     * the pre-race snapshot and propose the same conflicting number again.
     */
    private function insertOrderWithUniqueInvoiceNo(User $user, array $data): Order
    {
        $invoiceNo = $this->generateInvoiceNo($user->tenant_id);
        $delivery = $this->resolveDeliveryAttributes($data, null, $user->tenant_id);

        for ($attempt = 1; ; $attempt++) {
            try {
                return Order::create([
                    'tenant_id' => $user->tenant_id,
                    'user_id' => $user->id,
                    'invoice_no' => $invoiceNo,
                    'status' => OrderStatus::Draft,
                    'customer_name' => $data['customer_name'] ?? null,
                    'customer_phone' => $data['customer_phone'] ?? null,
                    'customer_address' => $data['customer_address'] ?? null,
                    'order_date' => $data['order_date'] ?? AppTime::toDateString(),
                    'notes' => $data['notes'] ?? null,
                    'ppn_percentage' => PpnSettings::percentage(),
                    'version' => 1,
                    'delivery_method' => $delivery['delivery_method'],
                    'delivery_fee' => $delivery['delivery_fee'],
                    'delivery_distance_km' => $delivery['delivery_distance_km'],
                ]);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 3) {
                    throw $e;
                }

                $invoiceNo = $this->nextInSequence($invoiceNo);
            }
        }
    }

    private function nextInSequence(string $number): string
    {
        return substr($number, 0, -3)
            .str_pad((string) (((int) substr($number, -3)) + 1), 3, '0', STR_PAD_LEFT);
    }

    public function update(Order $order, User $user, array $data): Order
    {
        if (! isset($data['version']) || (int) $data['version'] !== $order->version) {
            throw new OrderVersionMismatchException;
        }

        $updated = DB::transaction(function () use ($order, $user, $data) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ((int) $data['version'] !== $order->version) {
                throw new OrderVersionMismatchException;
            }

            // Once processing has started (diproses and beyond, or cancelled)
            // the order is an immutable transaction record: only status
            // transitions — and payments via their own endpoint — may touch
            // it. Any other field present in the payload is rejected.
            if (! in_array($order->status, [OrderStatus::Draft, OrderStatus::Pending], true)
                && collect($data)->except(['version', 'status', 'reason'])->isNotEmpty()) {
                throw new OrderLockedException(
                    "Order at status {$order->status->value} can no longer be edited; only status changes are allowed"
                );
            }

            $oldDate = $order->order_date?->toDateString();
            $newStatus = isset($data['status']) ? OrderStatus::from($data['status']) : null;
            $statusChanged = $newStatus && $newStatus !== $order->status;

            $order->fill(collect($data)->only([
                'customer_name', 'customer_phone', 'customer_address', 'notes',
            ])->toArray());

            if (isset($data['order_date']) && $data['order_date'] !== $oldDate) {
                OrderDateHistory::create([
                    'order_id' => $order->id,
                    'old_date' => $oldDate,
                    'new_date' => $data['order_date'],
                    'changed_by' => $user->id,
                    'reason' => $data['date_change_reason'] ?? null,
                    'created_at' => now(),
                ]);
                $order->order_date = $data['order_date'];
            }

            if (! empty($data['items']) && $order->status === OrderStatus::Draft) {
                $order->items()->delete();
                $this->syncItems($order, $data['items']);
            }

            if ($order->status === OrderStatus::Draft && $this->payloadHasDeliveryFields($data)) {
                $order->fill($this->resolveDeliveryAttributes($data, $order, $order->tenant_id));
            }

            if ($statusChanged) {
                $this->transitionStatus($order, $newStatus, $user, $data['reason'] ?? null);
            }

            $order->version = $order->version + 1;
            $order->save();

            $this->recalculateTotals($order->fresh());

            if (collect($data)->only(['customer_name', 'customer_phone', 'customer_address'])->isNotEmpty()) {
                Customer::upsertFromOrder($order);
            }

            $order = $order->fresh(['items', 'payments', 'statusLogs', 'dateHistories', 'user']);

            if ($statusChanged) {
                $this->sheetsSync->pushToOutbox($order);
            }

            return $order;
        });

        self::bumpAggregatesVersion($order->tenant_id);

        return $updated;
    }

    public function transitionStatus(Order $order, OrderStatus $target, User $user, ?string $reason = null): void
    {
        if (! $order->status->canTransitionTo($target)) {
            throw new InvalidStatusTransitionException($order->status->value, $target->value);
        }

        $from = $order->status;

        if ($target === OrderStatus::Pending && $from === OrderStatus::Draft) {
            $order->load(['items.productUnit.product', 'items.product.units']);
            $this->finalizeSnapshots($order, $user);
            $this->decrementStock($order, $user);
        }

        if ($target === OrderStatus::Cancelled && $from !== OrderStatus::Draft) {
            $order->load(['items.productUnit', 'items.batchAllocations']);
            $this->restoreStock($order, $user);
        }

        OrderStatusLog::create([
            'order_id' => $order->id,
            'from_status' => $from->value,
            'to_status' => $target->value,
            'changed_by' => $user->id,
            'reason' => $reason,
            'created_at' => now(),
        ]);

        activity()
            ->performedOn($order)
            ->causedBy($user)
            ->withProperties(['from' => $from->value, 'to' => $target->value, 'important' => true])
            ->log('status_changed');

        $order->status = $target;

        self::bumpAggregatesVersion($order->tenant_id);
    }

    private function syncItems(Order $order, array $items): void
    {
        $unitIds = collect($items)->pluck('product_unit_id')->unique()->values()->all();
        $units = TenantProductUnitGuard::manyForTenant($order->tenant_id, $unitIds);

        foreach ($items as $item) {
            $unit = $units->get($item['product_unit_id']);
            if (! $unit) {
                throw (new ModelNotFoundException)->setModel(
                    ProductUnit::class,
                    [$item['product_unit_id']],
                );
            }
            $product = $unit->product;
            $qty = (int) $item['quantity'];
            $priceCents = $this->toCents($unit->harga_jual);
            $costCents = $this->toCents($unit->harga_beli);
            $lineSubtotalCents = $priceCents * $qty;
            $discountCents = $this->discountAmountCents($lineSubtotalCents, $item);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_unit_id' => $unit->id,
                'product_name' => $product->nama,
                'satuan' => $unit->satuan,
                'price_snapshot' => $this->toAmount($priceCents),
                'cost_snapshot' => $this->toAmount($costCents),
                'quantity' => $qty,
                'discount_type' => $item['discount_type'] ?? null,
                'discount_value' => (float) ($item['discount_value'] ?? 0),
                'subtotal' => $this->toAmount($lineSubtotalCents - $discountCents),
            ]);
        }
    }

    /**
     * Convert a monetary value (float, numeric string, or decimal-cast
     * model attribute) into integer cents. PHP round() is half-away-from-
     * zero, i.e. half-up for the non-negative money values used here, so
     * binary-float noise below half a cent can never drift a persisted cent.
     */
    private function toCents(float|string|int|null $value): int
    {
        return (int) round((float) $value * 100);
    }

    /**
     * Render integer cents back to a decimal amount for the decimal:2
     * columns; cents are exact so no further rounding occurs.
     */
    private function toAmount(int $cents): float
    {
        return round($cents / 100, 2);
    }

    /**
     * Integer percent math with an explicit round-half-up step:
     * floor((cents * percentHundredths + 5000) / 10000). $percentHundredths
     * is the rate scaled by 100 (11% => 1100, 10.5% => 1050), keeping the
     * whole computation in exact integer arithmetic; the +5000 bias makes
     * the division round half-up instead of truncating.
     */
    private function percentOf(int $cents, int $percentHundredths): int
    {
        return (int) floor(($cents * $percentHundredths + 5000) / 10000);
    }

    /**
     * Line discount in integer cents, rounded once (half-up) at this
     * persistence boundary. For percent discounts the discount_value is
     * interpreted as hundredths of a percent (10 => 1000); fixed discounts
     * are plain money amounts converted to cents.
     */
    private function discountAmountCents(int $lineSubtotalCents, array $item): int
    {
        $valueCents = $this->toCents($item['discount_value'] ?? 0);
        if ($valueCents <= 0) {
            return 0;
        }

        if (($item['discount_type'] ?? null) === 'percent') {
            return min($this->percentOf($lineSubtotalCents, $valueCents), $lineSubtotalCents);
        }

        return min($valueCents, $lineSubtotalCents);
    }

    private function finalizeSnapshots(Order $order, User $user): void
    {
        foreach ($order->items as $item) {
            $unit = $item->productUnit ?? $item->product?->defaultUnit();
            if (! $unit) {
                continue;
            }

            $oldPriceCents = $this->toCents($item->price_snapshot);
            $oldCostCents = $this->toCents($item->cost_snapshot);
            $lineSubtotalCents = $this->toCents($unit->harga_jual) * (int) $item->quantity;
            $discountCents = $this->discountAmountCents($lineSubtotalCents, [
                'discount_type' => $item->discount_type,
                'discount_value' => $item->discount_value,
            ]);
            $newPriceCents = $this->toCents($unit->harga_jual);
            $newCostCents = $this->toCents($unit->harga_beli);

            $item->update([
                'product_name' => $unit->product->nama,
                'satuan' => $unit->satuan,
                'price_snapshot' => $this->toAmount($newPriceCents),
                'cost_snapshot' => $this->toAmount($newCostCents),
                'subtotal' => $this->toAmount($lineSubtotalCents - $discountCents),
            ]);

            if ($oldPriceCents !== $newPriceCents || $oldCostCents !== $newCostCents) {
                activity()
                    ->performedOn($order)
                    ->causedBy($user)
                    ->withProperties([
                        'order_item_id' => $item->id,
                        'product_name' => $unit->product->nama,
                        'old' => [
                            'price_snapshot' => $this->toAmount($oldPriceCents),
                            'cost_snapshot' => $this->toAmount($oldCostCents),
                        ],
                        'attributes' => [
                            'price_snapshot' => $this->toAmount($newPriceCents),
                            'cost_snapshot' => $this->toAmount($newCostCents),
                        ],
                        'important' => true,
                    ])
                    ->log('price_changed');
            }
        }
    }

    private function decrementStock(Order $order, User $user): void
    {
        foreach ($order->items as $item) {
            if (! $item->productUnit) {
                continue;
            }

            $this->stockMovement->decrement(
                $item->productUnit,
                (int) $item->quantity,
                StockMovementType::Sale,
                $user,
                $order,
            );

            foreach ($this->batches->allocateFefo($item->productUnit, (int) $item->quantity) as $allocation) {
                OrderItemBatch::create([
                    'order_item_id' => $item->id,
                    'product_batch_id' => $allocation['batch']->id,
                    'quantity' => $allocation['quantity'],
                ]);
            }
        }
    }

    private function restoreStock(Order $order, User $user, StockMovementType $type = StockMovementType::CancelRestore): void
    {
        foreach ($order->items as $item) {
            if (! $item->productUnit) {
                continue;
            }

            $this->stockMovement->increment(
                $item->productUnit,
                (int) $item->quantity,
                $type,
                $user,
                null,
                $order,
            );

            if ($item->batchAllocations->isNotEmpty()) {
                $this->batches->restore(
                    $item->batchAllocations
                        ->map(fn (OrderItemBatch $allocation) => [
                            'batch' => $allocation->product_batch_id,
                            'quantity' => $allocation->quantity,
                        ])
                        ->all(),
                );
            }
        }
    }

    /**
     * True when the payload carries any delivery field. Non-draft updates
     * containing delivery fields are ignored wholesale (same guard shape
     * as the items guard above); only money-affecting fields lock —
     * customer_address stays editable at any status.
     */
    private function payloadHasDeliveryFields(array $data): bool
    {
        return isset($data['delivery_method'])
            || isset($data['delivery_distance_km'])
            || isset($data['delivery_fee']);
    }

    /**
     * Canonical delivery resolution (plan "Fee resolution rules"):
     * diambil -> fee 0, km null; diantar -> explicit delivery_fee override
     * (toCents half-up), else current tenant settings via
     * DeliveryEstimateService::feeForKm (per_km clamped by min_fee; fixed
     * -> fixed_fee; per_km without km -> 0). Fields absent from the
     * payload fall back to the stored order, if any.
     *
     * @return array{delivery_method: string, delivery_fee: float, delivery_distance_km: ?float}
     */
    private function resolveDeliveryAttributes(array $data, ?Order $order, int $tenantId): array
    {
        $method = $data['delivery_method'] ?? $order?->delivery_method ?? 'diambil';

        if ($method !== 'diantar') {
            return ['delivery_method' => 'diambil', 'delivery_fee' => 0.0, 'delivery_distance_km' => null];
        }

        $km = isset($data['delivery_distance_km'])
            ? (float) $data['delivery_distance_km']
            : ($order?->delivery_distance_km !== null ? (float) $order->delivery_distance_km : null);

        $feeCents = isset($data['delivery_fee'])
            ? $this->toCents($data['delivery_fee'])
            : $this->deliveryFeeCents($tenantId, $km);

        return [
            'delivery_method' => 'diantar',
            'delivery_fee' => $this->toAmount(max(0, $feeCents)),
            'delivery_distance_km' => $km,
        ];
    }

    /**
     * Fee for a diantar order without an explicit override, resolved from
     * CURRENT tenant settings at write time (PPN semantics: owner rate
     * changes apply to unfinalized drafts). Reuses the canonical math in
     * DeliveryEstimateService::feeForKm; per_km with no distance has
     * nothing to compute from, so it stays 0.
     */
    private function deliveryFeeCents(int $tenantId, ?float $km): int
    {
        $settings = TenantSettings::for($tenantId);

        if ($km === null && $settings->deliveryFeeMode() !== 'fixed') {
            return 0;
        }

        return $this->toCents(
            DeliveryEstimateService::forTenant(Tenant::findOrFail($tenantId))->feeForKm((float) $km)
        );
    }

    public function recalculateTotals(Order $order): void
    {
        $order->load('items');
        $subtotalCents = $order->items->sum(
            fn ($i) => $this->toCents($i->price_snapshot) * (int) $i->quantity
        );
        $discountTotalCents = $order->items->sum(function ($i) {
            $lineSubtotalCents = $this->toCents($i->price_snapshot) * (int) $i->quantity;

            return $this->discountAmountCents($lineSubtotalCents, [
                'discount_type' => $i->discount_type,
                'discount_value' => $i->discount_value,
            ]);
        });
        $afterDiscountCents = $subtotalCents - $discountTotalCents;

        // PPN keeps its original structural basis: order-level, computed on
        // the after-discount base across all lines, rounded once (half-up)
        // here at the persistence boundary. The delivery fee is added
        // AFTER PPN — PPN never applies to ongkir.
        $ppnEnabled = PpnSettings::enabled();
        $ppnRate = PpnSettings::percentage();
        $ppnCents = $ppnEnabled
            ? $this->percentOf($afterDiscountCents, $this->toCents($ppnRate))
            : 0;

        $deliveryFeeCents = $this->toCents($order->delivery_fee);

        $order->update([
            'subtotal' => $this->toAmount($subtotalCents),
            'discount_total' => $this->toAmount($discountTotalCents),
            'ppn_percentage' => $ppnRate,
            'ppn_amount' => $this->toAmount($ppnCents),
            // Sum of the persisted parts; cents are exact, no re-rounding.
            'grand_total' => $this->toAmount($afterDiscountCents + $ppnCents + $deliveryFeeCents),
        ]);
    }

    public function softDelete(Order $order, User $user): void
    {
        DB::transaction(function () use ($order, $user) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            $restoresStock = $order->status !== OrderStatus::Draft
                && $order->status !== OrderStatus::Cancelled;

            if ($restoresStock) {
                $order->load(['items.productUnit', 'items.batchAllocations']);
                $this->restoreStock($order, $user, StockMovementType::DeleteRestore);
            }

            activity()
                ->performedOn($order)
                ->causedBy($user)
                ->withProperties(['important' => true, 'stock_restored' => $restoresStock])
                ->log('order_deleted');

            $order->delete();
        });

        self::bumpAggregatesVersion($order->tenant_id);
    }

    public function forceDelete(Order $order, User $user): void
    {
        DB::transaction(function () use ($order, $user) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            $restoresStock = $order->status !== OrderStatus::Draft
                && $order->status !== OrderStatus::Cancelled;

            if ($restoresStock) {
                $order->load(['items.productUnit', 'items.batchAllocations']);
                $this->restoreStock($order, $user, StockMovementType::DeleteRestore);
            }

            activity()
                ->performedOn($order)
                ->causedBy($user)
                ->withProperties(['important' => true, 'force' => true, 'stock_restored' => $restoresStock])
                ->log('order_force_deleted');

            $order->forceDelete();
        });

        self::bumpAggregatesVersion($order->tenant_id);
    }
}
