<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\ProductUnit;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StockMovementService
{
    public function apply(
        ProductUnit $unit,
        int $delta,
        StockMovementType $type,
        User $user,
        ?string $notes = null,
        ?Model $reference = null,
    ): StockMovement {
        return DB::transaction(function () use ($unit, $delta, $type, $user, $notes, $reference) {
            $locked = ProductUnit::query()->whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $before = (int) $locked->stok;
            $after = $before + $delta;

            if ($after < 0) {
                $locked->loadMissing('product');
                throw new InsufficientStockException(
                    $locked->product?->nama ?? 'Produk',
                    $before,
                    abs($delta),
                );
            }

            $locked->update(['stok' => $after]);

            return StockMovement::create([
                'tenant_id' => $user->tenant_id,
                'product_unit_id' => $locked->id,
                'type' => $type,
                'quantity_delta' => $delta,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'reference_type' => $reference ? $reference->getMorphClass() : null,
                'reference_id' => $reference?->getKey(),
                'notes' => $notes,
                'created_by' => $user->id,
                'created_at' => now(),
            ]);
        });
    }

    public function decrement(ProductUnit $unit, int $quantity, StockMovementType $type, User $user, ?Model $reference = null): StockMovement
    {
        return $this->apply($unit, -$quantity, $type, $user, null, $reference);
    }

    public function increment(ProductUnit $unit, int $quantity, StockMovementType $type, User $user, ?string $notes = null, ?Model $reference = null): StockMovement
    {
        return $this->apply($unit, $quantity, $type, $user, $notes, $reference);
    }
}
