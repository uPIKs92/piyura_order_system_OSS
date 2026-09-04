<?php

namespace Database\Factories;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use Database\Factories\Concerns\UsesDefaultTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    use UsesDefaultTenant;

    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn () => static::defaultTenantId(),
            'user_id' => null,
            'expense_date' => now()->toDateString(),
            'amount' => fake()->numberBetween(1000, 500000),
            'category' => fake()->randomElement(ExpenseCategory::cases())->value,
            'note' => fake()->optional()->sentence(),
        ];
    }
}
