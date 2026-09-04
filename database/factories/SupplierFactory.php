<?php

namespace Database\Factories;

use App\Models\Supplier;
use Database\Factories\Concerns\UsesDefaultTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    use UsesDefaultTenant;

    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn () => static::defaultTenantId(),
            'name' => fake()->company(),
            'phone' => fake()->optional()->phoneNumber(),
            'address' => fake()->optional()->address(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
