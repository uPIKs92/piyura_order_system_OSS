<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => (string) env('SEED_TENANT_SLUG', 'default'),
            'name' => (string) env('SEED_TENANT_NAME', 'My Shop'),
        ]);

        User::factory()->owner()->create([
            'name' => (string) env('SEED_OWNER_NAME', 'Owner'),
            'email' => (string) env('SEED_OWNER_EMAIL', 'owner@localhost'),
            'password' => (string) env('SEED_OWNER_PASSWORD', 'password'),
            'tenant_id' => $tenant->id,
        ]);
    }
}
