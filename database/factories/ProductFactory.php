<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use Database\Factories\Concerns\UsesDefaultTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    use UsesDefaultTenant;

    protected $model = Product::class;

    public function definition(): array
    {
        $nama = fake()->unique()->word();

        return [
            'category_id' => Category::factory(),
            'nama' => $nama,
            'slug' => Str::slug($nama),
            'sku' => fake()->unique()->bothify('SKU-####'),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Product $product) {
            if (! $product->tenant_id && $product->category_id) {
                $category = Category::withoutGlobalScopes()->find($product->category_id);
                if ($category) {
                    $product->tenant_id = $category->tenant_id;
                }
            }
        })->afterCreating(function (Product $product) {
            ProductUnit::factory()->for($product)->create([
                'is_default' => true,
                'satuan' => 'pcs',
            ]);
        });
    }
}
