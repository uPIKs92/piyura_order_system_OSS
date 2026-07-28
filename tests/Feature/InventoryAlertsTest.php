<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_alerts_when_stok_at_or_below_min_stok(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $low = Product::factory()->create(['nama' => 'Produk Rendah']);
        $lowUnit = $low->units()->first();
        $lowUnit->update(['stok' => 2, 'min_stok' => 5]);

        $ok = Product::factory()->create(['nama' => 'Produk Aman']);
        $okUnit = $ok->units()->first();
        $okUnit->update(['stok' => 20, 'min_stok' => 5]);

        $this->withToken($token)
            ->getJson('/api/inventory/alerts')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('items.0.product_unit_id', $lowUnit->id)
            ->assertJsonPath('items.0.stok', 2)
            ->assertJsonPath('items.0.min_stok', 5);
    }
}
