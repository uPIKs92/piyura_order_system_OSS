<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $price = fake()->randomFloat(2, 1000, 50000);
        $qty = fake()->numberBetween(1, 5);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_name' => fake()->words(2, true),
            'price_snapshot' => $price,
            'quantity' => $qty,
            'subtotal' => $price * $qty,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (OrderItem $item) {
            if ($item->product_id && ! $item->product_unit_id) {
                $product = Product::query()->with('units')->find($item->product_id);
                $unit = $product?->defaultUnit() ?? $product?->units->first();
                if ($unit) {
                    $item->product_unit_id = $unit->id;
                    $item->satuan = $unit->satuan;
                    $item->product_name = $product->nama;
                }
            }
        });
    }
}
