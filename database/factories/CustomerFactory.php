<?php

namespace Database\Factories;

use App\Models\Customer;
use Database\Factories\Concerns\UsesDefaultTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    use UsesDefaultTenant;

    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn () => static::defaultTenantId(),
            'name' => fake()->name(),
            'phone' => fake()->optional()->phoneNumber(),
            'address' => fake()->optional()->address(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
