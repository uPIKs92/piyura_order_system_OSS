<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductBatch>
 */
class ProductBatchFactory extends Factory
{
    protected $model = ProductBatch::class;

    public function definition(): array
    {
        return [
            'product_unit_id' => ProductUnit::factory(),
            'batch_no' => null,
            'expired_at' => null,
            'qty' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ProductBatch $batch) {
            if (! $batch->tenant_id && $batch->product_unit_id) {
                $unit = ProductUnit::query()->find($batch->product_unit_id);
                $product = $unit ? Product::withoutGlobalScopes()->find($unit->product_id) : null;
                if ($product) {
                    $batch->tenant_id = $product->tenant_id;
                }
            }
        });
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expired_at' => today()->subDay()]);
    }

    public function expiringIn(int $days): static
    {
        return $this->state(fn () => ['expired_at' => today()->addDays($days)]);
    }

    public function withBatchNo(string $no): static
    {
        return $this->state(fn () => ['batch_no' => $no]);
    }
}
