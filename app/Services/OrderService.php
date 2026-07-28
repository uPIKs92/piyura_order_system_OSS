<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\OrderVersionMismatchException;
use App\Models\Order;
use App\Models\OrderDateHistory;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\ProductUnit;
use App\Models\User;
use App\Support\PpnSettings;
use App\Support\TenantProductUnitGuard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private SheetsSyncService $sheetsSync,
        private StockMovementService $stockMovement,
    ) {}

    public function generateInvoiceNo(int $tenantId): string
    {
        $date = now()->format('Ymd');
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
        return DB::transaction(function () use ($user, $data) {
            $order = Order::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'invoice_no' => $this->generateInvoiceNo($user->tenant_id),
                'status' => OrderStatus::Draft,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'customer_address' => $data['customer_address'] ?? null,
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'ppn_percentage' => PpnSettings::percentage(),
                'version' => 1,
            ]);

            if (! empty($data['items'])) {
                $this->syncItems($order, $data['items']);
            }

            $this->recalculateTotals($order);

            return $order->fresh(['items', 'user']);
        });
    }

    public function update(Order $order, User $user, array $data): Order
    {
        if (! isset($data['version']) || (int) $data['version'] !== $order->version) {
            throw new OrderVersionMismatchException;
        }

        return DB::transaction(function () use ($order, $user, $data) {
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

            if ($statusChanged) {
                $this->transitionStatus($order, $newStatus, $user, $data['reason'] ?? null);
            }

            $order->version = $order->version + 1;
            $order->save();

            $this->recalculateTotals($order->fresh());

            $order = $order->fresh(['items', 'payments', 'statusLogs', 'dateHistories', 'user']);

            if ($statusChanged) {
                $this->sheetsSync->pushToOutbox($order);
            }

            return $order;
        });
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
            $order->load('items.productUnit');
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
            $price = (float) $unit->harga_jual;
            $lineSubtotal = $price * $qty;
            $discountAmount = $this->discountAmount($lineSubtotal, $item);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_unit_id' => $unit->id,
                'product_name' => $product->nama,
                'satuan' => $unit->satuan,
                'price_snapshot' => $price,
                'quantity' => $qty,
                'discount_type' => $item['discount_type'] ?? null,
                'discount_value' => (float) ($item['discount_value'] ?? 0),
                'subtotal' => $lineSubtotal - $discountAmount,
            ]);
        }
    }

    private function discountAmount(float $lineSubtotal, array $item): float
    {
        $value = (float) ($item['discount_value'] ?? 0);
        if ($value <= 0) {
            return 0;
        }

        if (($item['discount_type'] ?? null) === 'percent') {
            return $lineSubtotal * ($value / 100);
        }

        return min($value, $lineSubtotal);
    }

    private function finalizeSnapshots(Order $order, User $user): void
    {
        foreach ($order->items as $item) {
            $unit = $item->productUnit ?? $item->product?->defaultUnit();
            if (! $unit) {
                continue;
            }

            $oldPrice = (float) $item->price_snapshot;
            $lineSubtotal = (float) $unit->harga_jual * $item->quantity;
            $discountAmount = $this->discountAmount($lineSubtotal, [
                'discount_type' => $item->discount_type,
                'discount_value' => $item->discount_value,
            ]);
            $newPrice = (float) $unit->harga_jual;

            $item->update([
                'product_name' => $unit->product->nama,
                'satuan' => $unit->satuan,
                'price_snapshot' => $newPrice,
                'subtotal' => $lineSubtotal - $discountAmount,
            ]);

            if ($oldPrice !== $newPrice) {
                activity()
                    ->performedOn($order)
                    ->causedBy($user)
                    ->withProperties([
                        'order_item_id' => $item->id,
                        'product_name' => $unit->product->nama,
                        'old' => ['price_snapshot' => $oldPrice],
                        'attributes' => ['price_snapshot' => $newPrice],
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
        }
    }

    private function restoreStock(Order $order, User $user): void
    {
        foreach ($order->items as $item) {
            if (! $item->productUnit) {
                continue;
            }

            $this->stockMovement->increment(
                $item->productUnit,
                (int) $item->quantity,
                StockMovementType::CancelRestore,
                $user,
                null,
                $order,
            );
        }
    }

    public function recalculateTotals(Order $order): void
    {
        $order->load('items');
        $subtotal = $order->items->sum(fn ($i) => (float) $i->price_snapshot * $i->quantity);
        $discountTotal = $order->items->sum(function ($i) {
            $lineSubtotal = (float) $i->price_snapshot * $i->quantity;

            return $this->discountAmount($lineSubtotal, [
                'discount_type' => $i->discount_type,
                'discount_value' => $i->discount_value,
            ]);
        });
        $afterDiscount = $subtotal - $discountTotal;

        $ppnEnabled = PpnSettings::enabled();
        $ppnRate = PpnSettings::percentage();
        $ppnAmount = $ppnEnabled ? $afterDiscount * ($ppnRate / 100) : 0;

        $order->update([
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'ppn_percentage' => $ppnRate,
            'ppn_amount' => $ppnAmount,
            'grand_total' => $afterDiscount + $ppnAmount,
        ]);
    }

    public function softDelete(Order $order, User $user): void
    {
        activity()
            ->performedOn($order)
            ->causedBy($user)
            ->withProperties(['important' => true])
            ->log('order_deleted');

        $order->delete();
    }

    public function forceDelete(Order $order, User $user): void
    {
        activity()
            ->performedOn($order)
            ->causedBy($user)
            ->withProperties(['important' => true, 'force' => true])
            ->log('order_force_deleted');

        $order->forceDelete();
    }
}
