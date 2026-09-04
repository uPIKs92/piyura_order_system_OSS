<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\StockReceiptLine;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use App\Support\AppTime;
use App\Support\ExpirySettings;
use App\Support\TenantProductUnitGuard;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(
        private StockMovementService $stockMovement,
        private BatchService $batches,
    ) {}

    /**
     * Low-stock units, shallowest stock first, capped at 500 rows as a
     * pathological-growth guard (real tenants hold far fewer alert rows).
     *
     * @return array{count: int, items: Collection<int, array<string, mixed>>}
     */
    public function lowStockAlerts(): array
    {
        $items = ProductUnit::query()
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->whereColumn('stok', '<=', 'min_stok')
            ->with('product.category')
            ->orderBy('stok')
            ->limit(500)
            ->get()
            ->map(fn (ProductUnit $unit) => [
                'product_unit_id' => $unit->id,
                'product_id' => $unit->product_id,
                'product_name' => $unit->product?->nama,
                'category_name' => $unit->product?->category?->nama,
                'satuan' => $unit->satuan,
                'stok' => $unit->stok,
                'min_stok' => $unit->min_stok,
                'is_out_of_stock' => $unit->stok === 0,
            ]);

        return [
            'count' => $items->count(),
            'items' => $items->values(),
        ];
    }

    /**
     * Expiring batches bucketed into expired/near-expiry, soonest first,
     * capped at 500 rows as a pathological-growth guard (real tenants hold
     * far fewer alert rows).
     *
     * @return array{alert_days: int, expired: array<int, array<string, mixed>>, near_expiry: array<int, array<string, mixed>>, expired_count: int, near_expiry_count: int}
     */
    public function expiryAlerts(): array
    {
        $alertDays = ExpirySettings::alertDays();
        $today = today(AppTime::TZ)->startOfDay();

        $batches = ProductBatch::query()
            ->where('qty', '>', 0)
            ->whereNotNull('expired_at')
            ->whereDate('expired_at', '<=', $today->copy()->addDays($alertDays)->toDateString())
            ->with('productUnit.product.category')
            ->orderBy('expired_at')
            ->orderBy('id')
            ->limit(500)
            ->get();

        $expired = [];
        $nearExpiry = [];

        foreach ($batches as $batch) {
            $daysLeft = (int) round(($batch->expired_at->startOfDay()->getTimestamp() - $today->getTimestamp()) / 86400);

            $alert = [
                'id' => $batch->id,
                'product_unit_id' => $batch->product_unit_id,
                'product_id' => $batch->productUnit?->product_id,
                'product_name' => $batch->productUnit?->product?->nama,
                'category_name' => $batch->productUnit?->product?->category?->nama,
                'satuan' => $batch->productUnit?->satuan,
                'batch_no' => $batch->batch_no,
                'expired_at' => $batch->expired_at->toDateString(),
                'qty' => (int) $batch->qty,
                'days_left' => $daysLeft,
            ];

            if ($daysLeft < 0) {
                $expired[] = $alert;
            } elseif ($daysLeft <= $alertDays) {
                $nearExpiry[] = $alert;
            }
        }

        return [
            'alert_days' => $alertDays,
            'expired' => $expired,
            'near_expiry' => $nearExpiry,
            'expired_count' => count($expired),
            'near_expiry_count' => count($nearExpiry),
        ];
    }

    public function movements(array $filters = []): LengthAwarePaginator
    {
        $query = StockMovement::query()
            ->with(['productUnit.product', 'creator:id,name'])
            ->latest('created_at');

        if (! empty($filters['product_unit_id'])) {
            $query->where('product_unit_id', $filters['product_unit_id']);
        }

        if (! empty($filters['product_id'])) {
            $query->whereHas('productUnit', fn ($q) => $q->where('product_id', $filters['product_id']));
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', AppTime::dayStartUtc($filters['from']));
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', AppTime::dayEndUtc($filters['to']));
        }

        return $query->paginate((int) ($filters['per_page'] ?? 50));
    }

    public function restock(
        ProductUnit $unit,
        User $user,
        int $quantity,
        ?string $notes = null,
        ?string $expiredAt = null,
        ?string $batchNo = null,
    ): StockMovement {
        return DB::transaction(function () use ($unit, $user, $quantity, $notes, $expiredAt, $batchNo) {
            $movement = $this->stockMovement->increment(
                $unit,
                $quantity,
                StockMovementType::Restock,
                $user,
                $notes,
            );

            $this->batches->receive($unit, $quantity, $expiredAt, $batchNo);

            return $movement;
        });
    }

    /**
     * Stock opname: reconcile counted quantities against system stock per unit.
     *
     * @param  array<int, array{product_unit_id: int, counted_qty: int}>  $items
     * @return array<int, array{product_unit_id: int, product_name: string|null, satuan: string|null, system_qty: int, counted_qty: int, diff: int}>
     */
    public function opname(User $user, array $items, ?string $notes = null): array
    {
        $units = TenantProductUnitGuard::manyForTenant($user->tenant_id, array_column($items, 'product_unit_id'));

        $results = [];

        foreach ($items as $item) {
            $unit = $units[(int) $item['product_unit_id']];

            $results[] = DB::transaction(function () use ($user, $unit, $item, $notes) {
                $locked = ProductUnit::query()->whereKey($unit->id)->lockForUpdate()->firstOrFail();
                $systemQty = (int) $locked->stok;
                $countedQty = (int) $item['counted_qty'];
                $diff = $countedQty - $systemQty;

                if ($diff < 0) {
                    $this->batches->allocateFefo($locked, abs($diff));
                } elseif ($diff > 0) {
                    $this->batches->receive($locked, $diff);
                }

                if ($diff !== 0) {
                    $this->stockMovement->apply($locked, $diff, StockMovementType::Adjustment, $user, $notes, null, null);
                }

                return [
                    'product_unit_id' => $locked->id,
                    'product_name' => $unit->product?->nama,
                    'satuan' => $unit->satuan,
                    'system_qty' => $systemQty,
                    'counted_qty' => $countedQty,
                    'diff' => $diff,
                ];
            });
        }

        return $results;
    }

    /**
     * @param  array{supplier_name?: string, supplier_id?: int, notes?: string, received_at?: string, items: array<int, array{product_unit_id: int, quantity: int, unit_cost?: float, expired_at?: string, batch_no?: string}>}  $data
     */
    public function receiveBatch(User $user, array $data): StockReceipt
    {
        return DB::transaction(function () use ($user, $data) {
            $receipt = $this->insertReceiptWithUniqueReceiptNo($user, $data);

            foreach ($data['items'] as $line) {
                $unit = TenantProductUnitGuard::findForTenant($user->tenant_id, (int) $line['product_unit_id']);

                StockReceiptLine::create([
                    'stock_receipt_id' => $receipt->id,
                    'product_unit_id' => $unit->id,
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'] ?? null,
                    'expired_at' => $line['expired_at'] ?? null,
                    'batch_no' => $line['batch_no'] ?? null,
                ]);

                $this->stockMovement->increment(
                    $unit,
                    (int) $line['quantity'],
                    StockMovementType::Receive,
                    $user,
                    $data['notes'] ?? null,
                    $receipt,
                );

                $this->batches->receive(
                    $unit,
                    (int) $line['quantity'],
                    $line['expired_at'] ?? null,
                    $line['batch_no'] ?? null,
                );
            }

            return $receipt->load('lines.productUnit.product');
        });
    }

    /**
     * Insert the receipt row, retrying with the next receipt number when a
     * concurrent receive grabbed the same number. A duplicate-key failure is
     * statement-scoped, so the surrounding transaction stays usable and only
     * the receipt number is regenerated between attempts (incremented from
     * the attempted sequence so a REPEATABLE READ snapshot cannot loop on
     * the same conflicting number).
     */
    private function insertReceiptWithUniqueReceiptNo(User $user, array $data): StockReceipt
    {
        $receiptNo = $this->generateReceiptNo();

        for ($attempt = 1; ; $attempt++) {
            try {
                return StockReceipt::create([
                    'tenant_id' => $user->tenant_id,
                    'receipt_no' => $receiptNo,
                    'supplier_name' => $data['supplier_name'] ?? null,
                    'supplier_id' => $data['supplier_id'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'received_at' => $data['received_at'] ?? now(),
                    'created_by' => $user->id,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 3) {
                    throw $e;
                }

                $receiptNo = substr($receiptNo, 0, -3)
                    .str_pad((string) (((int) substr($receiptNo, -3)) + 1), 3, '0', STR_PAD_LEFT);
            }
        }
    }

    private function generateReceiptNo(): string
    {
        $date = AppTime::now()->format('Ymd');
        $prefix = "RCV-{$date}-";
        $last = StockReceipt::where('receipt_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('receipt_no');

        $seq = $last ? ((int) substr($last, -3)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
