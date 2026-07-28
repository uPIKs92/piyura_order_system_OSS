<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductUnit>
 */
class ProductUnitFactory extends Factory
{
    protected $model = ProductUnit::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'satuan' => 'pcs',
            'harga_jual' => fake()->randomFloat(2, 1000, 100000),
            'harga_beli' => fake()->randomFloat(2, 500, 90000),
            'stok' => fake()->numberBetween(0, 500),
            'is_default' => true,
        ];
    }
}
