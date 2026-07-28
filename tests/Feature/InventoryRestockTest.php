<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryRestockTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_restock_product_unit(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;
        $product = Product::factory()->create();
        $unit = $product->units()->first();
        $unit->update(['stok' => 3, 'min_stok' => 5]);

        $this->withToken($token)
            ->postJson("/api/product-units/{$unit->id}/restock", [
                'quantity' => 10,
                'notes' => 'Restok gudang',
            ])
            ->assertOk()
            ->assertJsonPath('unit.stok', 13);

        $this->assertEquals(13, $unit->fresh()->stok);
        $this->assertDatabaseHas('stock_movements', [
            'product_unit_id' => $unit->id,
            'type' => StockMovementType::Restock->value,
            'quantity_delta' => 10,
            'quantity_before' => 3,
            'quantity_after' => 13,
            'created_by' => $owner->id,
        ]);
    }

    public function test_staff_cannot_restock(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;
        $unit = Product::factory()->create()->units()->first();

        $this->withToken($token)
            ->postJson("/api/product-units/{$unit->id}/restock", ['quantity' => 5])
            ->assertForbidden();
    }
}
