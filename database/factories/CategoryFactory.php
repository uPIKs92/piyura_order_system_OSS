<?php

namespace Database\Factories;

use App\Models\Category;
use Database\Factories\Concerns\UsesDefaultTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    use UsesDefaultTenant;

    protected $model = Category::class;

    public function definition(): array
    {
        $nama = fake()->unique()->word();

        return [
            'tenant_id' => fn () => static::defaultTenantId(),
            'nama' => $nama,
            'slug' => Str::slug($nama),
        ];
    }
}
