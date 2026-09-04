<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\ExpiredStockException;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Models\StockMovement;
use App\Models\User;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BatchService
{
    public function __construct(private readonly StockMovementService $stockMovements)
    {
    }

    /**
     * Receive stock into a batch. Merges into an existing batch with the same
     * (product_unit_id, batch_no, expired_at) or creates a new one.
     *
     * Does not open its own transaction: callers must wrap in DB::transaction().
     *
     * @param  string|null  $expiredAt  'Y-m-d' string or null.
     * @param  string|null  $batchNo  Batch number or null (whitespace-only becomes null).
     */
    public function receive(ProductUnit $unit, int $quantity, ?string $expiredAt = null, ?string $batchNo = null): ProductBatch
    {
        $batchNo = trim((string) $batchNo);
        $batchNo = $batchNo === '' ? null : $batchNo;

        $expiredAt = trim((string) $expiredAt);
        $expiredAt = $expiredAt === '' ? null : $expiredAt;

        $batch = ProductBatch::query()
            ->where('product_unit_id', $unit->id)
            ->when(
                $batchNo === null,
                fn ($query) => $query->whereNull('batch_no'),
                fn ($query) => $query->where('batch_no', $batchNo),
            )
            ->when(
                $expiredAt === null,
                fn ($query) => $query->whereNull('expired_at'),
                fn ($query) => $query->whereDate('expired_at', $expiredAt),
            )
            ->lockForUpdate()
            ->first();

        if ($batch) {
            $batch->increment('qty', $quantity);

            return $batch;
        }

        $tenantId = DB::table('products')->where('id', $unit->product_id)->value('tenant_id');

        return ProductBatch::create([
            'tenant_id' => $tenantId,
            'product_unit_id' => $unit->id,
            'batch_no' => $batchNo,
            'expired_at' => $expiredAt,
            'qty' => $quantity,
        ]);
    }

    /**
     * Allocate quantity FEFO (earliest expiry first, undated last), skipping
     * batches already expired. Batches expiring today are still sellable.
     *
     * Lock ordering: the caller must already hold a lock on the ProductUnit
     * row before calling (unit first, then batches). No internal transaction.
     *
     * @return array<int, array{batch: ProductBatch, quantity: int}>
     *
     * @throws ExpiredStockException When unexpired stock cannot cover quantity.
     */
    public function allocateFefo(ProductUnit $unit, int $quantity): array
    {
        $batches = $this->lockedBatches($unit);

        if ($batches->isEmpty() && (int) $unit->stok > 0) {
            $batchCount = (int) ProductBatch::query()
                ->where('product_unit_id', $unit->id)
                ->lockForUpdate()
                ->count();

            if ($batchCount === 0) {
                $this->receive($unit, (int) $unit->stok);
                $batches = $this->lockedBatches($unit);
            }
        }

        $unexpiredAvailable = 0;
        $expiredQty = 0;

        foreach ($batches as $batch) {
            if ($this->isExpired($batch)) {
                $expiredQty += $batch->qty;
                continue;
            }
            $unexpiredAvailable += $batch->qty;
        }

        if ($unexpiredAvailable < $quantity) {
            $unit->loadMissing('product');

            throw new ExpiredStockException(
                $unit->product?->nama ?? 'Produk',
                $unexpiredAvailable,
                $expiredQty,
                $quantity,
            );
        }

        $allocations = [];
        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining === 0 || $this->isExpired($batch)) {
                continue;
            }

            $take = min($remaining, $batch->qty);
            if ($take <= 0) {
                continue;
            }

            $batch->decrement('qty', $take);
            $allocations[] = ['batch' => $batch, 'quantity' => $take];
            $remaining -= $take;
        }

        return $allocations;
    }

    /**
     * Restore quantities previously allocated by allocateFefo().
     *
     * @param  array<int, array{batch: ProductBatch|int, quantity: int}>  $allocations
     */
    public function restore(array $allocations): void
    {
        foreach ($allocations as $allocation) {
            $id = $allocation['batch'] instanceof ProductBatch
                ? $allocation['batch']->getKey()
                : $allocation['batch'];

            ProductBatch::withoutGlobalScope(TenantScope::class)
                ->whereKey($id)
                ->increment('qty', $allocation['quantity']);
        }
    }

    /**
     * Write off an entire (expired) batch: sets its qty to 0 and records a
     * write_off stock movement referencing the batch.
     *
     * @throws InvalidArgumentException When the batch qty is already zero.
     */
    public function writeOff(ProductBatch $batch, User $user, ?string $reason = null): StockMovement
    {
        return DB::transaction(function () use ($batch, $user, $reason) {
            $unit = ProductUnit::query()->whereKey($batch->product_unit_id)->lockForUpdate()->firstOrFail();

            $locked = ProductBatch::withoutGlobalScope(TenantScope::class)
                ->whereKey($batch->id)
                ->lockForUpdate()
                ->firstOrFail();

            $qty = (int) $locked->qty;

            if ($qty === 0) {
                throw new InvalidArgumentException('Batch qty is already zero.');
            }

            $locked->update(['qty' => 0]);

            return $this->stockMovements->apply(
                $unit,
                -$qty,
                StockMovementType::WriteOff,
                $user,
                $reason,
                $locked,
                $locked->id,
            );
        });
    }

    private function lockedBatches(ProductUnit $unit): Collection
    {
        return ProductBatch::query()
            ->where('product_unit_id', $unit->id)
            ->where('qty', '>', 0)
            ->orderByRaw('expired_at IS NULL ASC')
            ->orderBy('expired_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    private function isExpired(ProductBatch $batch): bool
    {
        return $batch->expired_at !== null
            && $batch->expired_at->isBefore(today(config('app.timezone')));
    }
}
