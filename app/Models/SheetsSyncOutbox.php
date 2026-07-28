<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SheetsSyncOutbox extends Model
{
    use BelongsToTenant;

    protected $table = 'sheets_sync_outbox';

    protected $fillable = [
        'tenant_id', 'order_id', 'idempotency_key', 'payload', 'status',
        'attempts', 'last_error', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
