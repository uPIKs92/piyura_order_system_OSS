<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'phone', 'address', 'latitude', 'longitude', 'notes', 'last_ordered_at', 'orders_count',
    ];

    protected function casts(): array
    {
        return [
            'last_ordered_at' => 'datetime',
            'orders_count' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public static function upsertFromOrder(Order $order): ?Customer
    {
        $phone = trim((string) $order->customer_phone);

        if ($phone === '') {
            return null;
        }

        $name = trim((string) $order->customer_name);

        $customer = static::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('phone', $phone)
            ->first();

        if ($customer !== null) {
            if ($name !== '') {
                $customer->name = $name;
            }

            if (filled($order->customer_address)) {
                $customer->address = $order->customer_address;
            }

            $customer->applyLastOrderedAt($order);

            if ($customer->isDirty()) {
                $customer->save();
            }

            return $customer;
        }

        if ($name === '') {
            return null;
        }

        $claimable = static::query()
            ->where('tenant_id', $order->tenant_id)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->where(fn ($query) => $query->whereNull('phone')->orWhere('phone', ''))
            ->orderBy('id')
            ->first();

        if ($claimable !== null) {
            $claimable->phone = $phone;

            if (filled($order->customer_address)) {
                $claimable->address = $order->customer_address;
            }

            $claimable->applyLastOrderedAt($order);
            $claimable->save();

            return $claimable;
        }

        return static::create([
            'tenant_id' => $order->tenant_id,
            'name' => $name,
            'phone' => $phone,
            'address' => filled($order->customer_address) ? $order->customer_address : null,
            'last_ordered_at' => $order->order_date,
        ]);
    }

    private function applyLastOrderedAt(Order $order): void
    {
        $orderDate = $order->order_date;

        if ($orderDate !== null && ($this->last_ordered_at === null || $orderDate->gt($this->last_ordered_at))) {
            $this->last_ordered_at = $orderDate;
        }
    }
}
