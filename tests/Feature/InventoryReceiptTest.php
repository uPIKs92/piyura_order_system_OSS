<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_receive_batch_stock(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $productA = Product::factory()->create();
        $unitA = $productA->units()->first();
        $unitA->update(['stok' => 5]);

        $productB = Product::factory()->create();
        $unitB = $productB->units()->first();
        $unitB->update(['stok' => 0]);

        $response = $this->withToken($token)
            ->postJson('/api/inventory/receipts', [
                'supplier_name' => 'Supplier ABC',
                'notes' => 'Pengiriman pagi',
                'items' => [
                    ['product_unit_id' => $unitA->id, 'quantity' => 10, 'unit_cost' => 5000],
                    ['product_unit_id' => $unitB->id, 'quantity' => 25],
                ],
            ])
            ->assertCreated()
            ->assertJsonStructure(['receipt_no', 'lines']);

        $this->assertEquals(15, $unitA->fresh()->stok);
        $this->assertEquals(25, $unitB->fresh()->stok);
        $this->assertDatabaseCount('stock_receipts', 1);
        $this->assertDatabaseCount('stock_receipt_lines', 2);
        $this->assertDatabaseHas('stock_movements', [
            'product_unit_id' => $unitA->id,
            'type' => StockMovementType::Receive->value,
            'quantity_delta' => 10,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_unit_id' => $unitB->id,
            'type' => StockMovementType::Receive->value,
            'quantity_delta' => 25,
        ]);

        $receiptId = $response->json('id');
        $this->assertNotNull(StockReceipt::find($receiptId));
    }
}
