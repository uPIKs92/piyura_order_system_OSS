<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'user_id', 'invoice_no', 'status', 'customer_name', 'customer_phone',
        'customer_address', 'order_date', 'subtotal', 'discount_total',
        'ppn_percentage', 'ppn_amount', 'grand_total', 'total_paid', 'change_due', 'notes', 'version',
        'mayar_qr_url', 'mayar_amount',
        'delivery_method', 'delivery_fee', 'delivery_distance_km',
    ];

    protected $appends = ['payment_status', 'remaining_amount', 'change_amount'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'order_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'ppn_percentage' => 'decimal:2',
            'ppn_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'change_due' => 'decimal:2',
            'version' => 'integer',
            'mayar_amount' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'delivery_distance_km' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class);
    }

    public function dateHistories(): HasMany
    {
        return $this->hasMany(OrderDateHistory::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function syncTotalPaid(): void
    {
        $this->update([
            'total_paid' => $this->payments()->sum('amount'),
            'change_due' => $this->payments()->sum('change_amount'),
        ]);
    }

    public function getPaymentStatusAttribute(): string
    {
        return $this->paymentStatus();
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) $this->grand_total - (float) $this->total_paid);
    }

    public function getChangeAmountAttribute(): float
    {
        return (float) ($this->change_due ?? 0);
    }

    public function paymentStatus(): string
    {
        $paid = (float) $this->total_paid;
        $total = (float) $this->grand_total;

        if ($paid <= 0) {
            return 'unpaid';
        }
        if ($paid < $total) {
            return 'partial';
        }
        if ($paid > $total) {
            return 'overpaid';
        }

        return 'paid';
    }
}
