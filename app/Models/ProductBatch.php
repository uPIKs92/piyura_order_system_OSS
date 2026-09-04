<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBatch extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'product_unit_id', 'batch_no', 'expired_at', 'qty',
    ];

    protected function casts(): array
    {
        return [
            'expired_at' => 'date',
            'qty' => 'integer',
        ];
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function isExpired(): bool
    {
        return $this->expired_at !== null
            && $this->expired_at->copy()->endOfDay()->isPast();
    }

    public function scopeUnexpired(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->whereNull('expired_at')->orWhere('expired_at', '>=', today());
        });
    }
}
