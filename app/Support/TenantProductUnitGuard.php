<?php

namespace App\Support;

use App\Models\ProductUnit;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TenantProductUnitGuard
{
    public static function findForTenant(int $tenantId, int $unitId): ProductUnit
    {
        $unit = ProductUnit::query()
            ->with('product')
            ->whereKey($unitId)
            ->whereHas('product', fn ($query) => $query->where('tenant_id', $tenantId))
            ->first();

        if (! $unit) {
            throw (new ModelNotFoundException)->setModel(ProductUnit::class, [$unitId]);
        }

        return $unit;
    }

    /**
     * @param  array<int, int|string>  $unitIds
     * @return \Illuminate\Support\Collection<int, ProductUnit>
     */
    public static function manyForTenant(int $tenantId, array $unitIds): \Illuminate\Support\Collection
    {
        $ids = collect($unitIds)->map(fn ($id) => (int) $id)->unique()->values()->all();

        $units = ProductUnit::query()
            ->with('product')
            ->whereIn('id', $ids)
            ->whereHas('product', fn ($query) => $query->where('tenant_id', $tenantId))
            ->get()
            ->keyBy('id');

        foreach ($ids as $id) {
            if (! $units->has($id)) {
                throw (new ModelNotFoundException)->setModel(ProductUnit::class, [$id]);
            }
        }

        return $units;
    }
}
