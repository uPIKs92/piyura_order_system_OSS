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

    public function test_receive_retries_when_receipt_number_collides(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $product = Product::factory()->create();
        $unit = $product->units()->first();

        // generateReceiptNo() reads the latest-id row with today's prefix, so
        // -003 at the higher id makes it propose -004, which already exists at
        // the lower id — forcing the duplicate-key retry path.
        $today = now()->format('Ymd');
        StockReceipt::create([
            'tenant_id' => $owner->tenant_id,
            'receipt_no' => "RCV-{$today}-004",
            'received_at' => now(),
            'created_by' => $owner->id,
        ]);
        StockReceipt::create([
            'tenant_id' => $owner->tenant_id,
            'receipt_no' => "RCV-{$today}-003",
            'received_at' => now(),
            'created_by' => $owner->id,
        ]);

        $this->withToken($token)
            ->postJson('/api/inventory/receipts', [
                'items' => [
                    ['product_unit_id' => $unit->id, 'quantity' => 5],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('receipt_no', "RCV-{$today}-005");

        $this->assertDatabaseHas('stock_receipts', ['receipt_no' => "RCV-{$today}-005"]);
    }
}
