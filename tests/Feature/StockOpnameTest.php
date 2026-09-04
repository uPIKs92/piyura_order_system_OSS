<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\User;
use Database\Factories\ProductBatchFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockOpnameTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private string $token;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
        $this->token = $this->owner->createToken('test')->plainTextToken;
        $this->product = Product::factory()->create();
    }

    public function test_decrease_applies_fefo_across_batches(): void
    {
        $unit = $this->product->units()->first();
        $unit->update(['stok' => 10]);

        $early = ProductBatchFactory::new()->for($unit)->withBatchNo('B1')->expiringIn(5)->create(['qty' => 6]);
        $late = ProductBatchFactory::new()->for($unit)->withBatchNo('B2')->expiringIn(20)->create(['qty' => 4]);

        $this->withToken($this->token)
            ->postJson('/api/inventory/opname', [
                'notes' => 'Opname bulanan',
                'items' => [
                    ['product_unit_id' => $unit->id, 'counted_qty' => 8],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.product_unit_id', $unit->id)
            ->assertJsonPath('results.0.product_name', $this->product->nama)
            ->assertJsonPath('results.0.satuan', $unit->satuan)
            ->assertJsonPath('results.0.system_qty', 10)
            ->assertJsonPath('results.0.counted_qty', 8)
            ->assertJsonPath('results.0.diff', -2);

        $this->assertSame(4, (int) $early->fresh()->qty);
        $this->assertSame(4, (int) $late->fresh()->qty);
        $this->assertSame(8, (int) $unit->fresh()->stok);

        $this->assertDatabaseHas('stock_movements', [
            'product_unit_id' => $unit->id,
            'type' => StockMovementType::Adjustment->value,
            'quantity_delta' => -2,
            'quantity_before' => 10,
            'quantity_after' => 8,
            'created_by' => $this->owner->id,
        ]);
    }

    public function test_increase_goes_to_undated_batch(): void
    {
        $unit = $this->product->units()->first();
        $unit->update(['stok' => 3]);

        ProductBatchFactory::new()->for($unit)->withBatchNo('B1')->expiringIn(5)->create(['qty' => 3]);

        $this->withToken($this->token)
            ->postJson('/api/inventory/opname', [
                'items' => [
                    ['product_unit_id' => $unit->id, 'counted_qty' => 5],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.diff', 2);

        $this->assertSame(5, (int) $unit->fresh()->stok);

        $this->assertDatabaseHas('product_batches', [
            'product_unit_id' => $unit->id,
            'batch_no' => null,
            'expired_at' => null,
            'qty' => 2,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_unit_id' => $unit->id,
            'type' => StockMovementType::Adjustment->value,
            'quantity_delta' => 2,
            'quantity_before' => 3,
            'quantity_after' => 5,
        ]);
    }

    public function test_zero_diff_writes_no_movement(): void
    {
        $unit = $this->product->units()->first();
        $unit->update(['stok' => 4]);

        $this->withToken($this->token)
            ->postJson('/api/inventory/opname', [
                'items' => [
                    ['product_unit_id' => $unit->id, 'counted_qty' => 4],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.diff', 0)
            ->assertJsonPath('results.0.system_qty', 4)
            ->assertJsonPath('results.0.counted_qty', 4);

        $this->assertSame(4, (int) $unit->fresh()->stok);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_insufficient_unexpired_stock_returns_422(): void
    {
        $unit = $this->product->units()->first();
        $unit->update(['stok' => 10]);

        $expired = ProductBatchFactory::new()->for($unit)->expired()->create(['qty' => 10]);

        $this->withToken($this->token)
            ->postJson('/api/inventory/opname', [
                'items' => [
                    ['product_unit_id' => $unit->id, 'counted_qty' => 8],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => sprintf(
                    'Stok %s tidak cukup (stok belum kedaluwarsa: 0, dibutuhkan: 2). Stok kedaluwarsa: 10 — lakukan write-off atau restock.',
                    $this->product->nama,
                ),
            ]);

        $this->assertSame(10, (int) $expired->fresh()->qty);
        $this->assertSame(10, (int) $unit->fresh()->stok);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_multiple_units_and_distinct_validation(): void
    {
        $unit = $this->product->units()->first();
        $unit->update(['stok' => 6]);

        $other = Product::factory()->create();
        $otherUnit = $other->units()->first();
        $otherUnit->update(['stok' => 1]);

        $this->withToken($this->token)
            ->postJson('/api/inventory/opname', [
                'items' => [
                    ['product_unit_id' => $unit->id, 'counted_qty' => 6],
                    ['product_unit_id' => $otherUnit->id, 'counted_qty' => 3],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'results')
            ->assertJsonPath('results.0.diff', 0)
            ->assertJsonPath('results.1.product_unit_id', $otherUnit->id)
            ->assertJsonPath('results.1.diff', 2);

        $this->assertSame(3, (int) $otherUnit->fresh()->stok);

        $this->withToken($this->token)
            ->postJson('/api/inventory/opname', [
                'items' => [
                    ['product_unit_id' => $unit->id, 'counted_qty' => 6],
                    ['product_unit_id' => $unit->id, 'counted_qty' => 7],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_staff_forbidden(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;
        $unit = $this->product->units()->first();

        $this->withToken($token)
            ->postJson('/api/inventory/opname', [
                'items' => [
                    ['product_unit_id' => $unit->id, 'counted_qty' => 1],
                ],
            ])
            ->assertForbidden();
    }
}
