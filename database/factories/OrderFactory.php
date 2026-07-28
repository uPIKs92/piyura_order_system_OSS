<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Database\Factories\Concerns\UsesDefaultTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    use UsesDefaultTenant;

    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn () => static::defaultTenantId(),
            'user_id' => User::factory(),
            'invoice_no' => 'INV-'.now()->format('Ymd').'-'.fake()->unique()->numerify('###'),
            'status' => OrderStatus::Draft,
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->phoneNumber(),
            'order_date' => now()->toDateString(),
            'subtotal' => 0,
            'discount_total' => 0,
            'ppn_percentage' => 11.00,
            'ppn_amount' => 0,
            'grand_total' => 0,
            'total_paid' => 0,
            'version' => 1,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => OrderStatus::Pending]);
    }
}
