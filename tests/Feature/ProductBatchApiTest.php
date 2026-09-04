<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use Database\Factories\ProductBatchFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBatchApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private string $token;

    private Product $product;

    private ProductUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
        $this->token = $this->owner->createToken('test')->plainTextToken;
        $this->product = Product::factory()->create();
        $this->unit = $this->product->units()->first();
    }

    public function test_index_filters_by_product_unit_and_excludes_zero_qty(): void
    {
        $other = ProductUnit::factory()->for($this->product)->create(['satuan' => 'box']);

        $batch = ProductBatchFactory::new()->for($this->unit)->withBatchNo('B1')->expiringIn(5)->create(['qty' => 10]);
        ProductBatchFactory::new()->for($other)->expiringIn(7)->create(['qty' => 4]);
        ProductBatchFactory::new()->for($this->unit)->expiringIn(9)->create(['qty' => 0]);

        $this->withToken($this->token)
            ->getJson("/api/product-batches?product_unit_id={$this->unit->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $batch->id)
            ->assertJsonPath('data.0.product_unit_id', $this->unit->id)
            ->assertJsonPath('data.0.product_id', $this->product->id)
            ->assertJsonPath('data.0.product_name', $this->product->nama)
            ->assertJsonPath('data.0.category_name', $this->product->category?->nama)
            ->assertJsonPath('data.0.satuan', $this->unit->satuan)
            ->assertJsonPath('data.0.batch_no', 'B1')
            ->assertJsonPath('data.0.expired_at', today()->addDays(5)->toDateString())
            ->assertJsonPath('data.0.qty', 10);
    }

    public function test_index_filter_expired_and_expiring(): void
    {
        $expired = ProductBatchFactory::new()->for($this->unit)->expired()->create(['qty' => 3]);
        $todayBatch = ProductBatchFactory::new()->for($this->unit)->expiringIn(0)->create(['qty' => 2]);
        $soon = ProductBatchFactory::new()->for($this->unit)->expiringIn(10)->create(['qty' => 4]);
        ProductBatchFactory::new()->for($this->unit)->expiringIn(60)->create(['qty' => 5]);
        ProductBatchFactory::new()->for($this->unit)->create(['qty' => 6]);

        $this->withToken($this->token)
            ->getJson('/api/product-batches?filter=expired')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $expired->id);

        $this->withToken($this->token)
            ->getJson('/api/product-batches?filter=expiring')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $todayBatch->id)
            ->assertJsonPath('data.1.id', $soon->id);

        $this->withToken($this->token)
            ->getJson('/api/product-batches?filter=all')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_index_orders_expired_at_asc_nulls_last(): void
    {
        $late = ProductBatchFactory::new()->for($this->unit)->expiringIn(10)->create(['qty' => 1]);
        $early = ProductBatchFactory::new()->for($this->unit)->expiringIn(2)->create(['qty' => 1]);
        $undated = ProductBatchFactory::new()->for($this->unit)->create(['qty' => 1]);

        $this->withToken($this->token)
            ->getJson('/api/product-batches')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $early->id)
            ->assertJsonPath('data.1.id', $late->id)
            ->assertJsonPath('data.2.id', $undated->id)
            ->assertJsonPath('data.2.expired_at', null);
    }

    public function test_index_is_paginated(): void
    {
        ProductBatchFactory::new()->for($this->unit)->count(3)->sequence(
            ['qty' => 1],
            ['qty' => 2],
            ['qty' => 3],
        )->create();

        $this->withToken($this->token)
            ->getJson('/api/product-batches')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('per_page', 20)
            ->assertJsonPath('total', 3)
            ->assertJsonPath('last_page', 1);

        $this->withToken($this->token)
            ->getJson('/api/product-batches?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('current_page', 2);

        $this->withToken($this->token)
            ->getJson('/api/product-batches?per_page=500')
            ->assertStatus(422);
    }

    public function test_write_off_zeroes_qty_and_writes_movement(): void
    {
        $this->unit->update(['stok' => 8]);
        $batch = ProductBatchFactory::new()->for($this->unit)->withBatchNo('B1')->expired()->create(['qty' => 8]);

        $this->withToken($this->token)
            ->postJson("/api/product-batches/{$batch->id}/write-off", ['reason' => 'Kedaluwarsa'])
            ->assertOk()
            ->assertJsonPath('id', $batch->id)
            ->assertJsonPath('qty', 0)
            ->assertJsonPath('batch_no', 'B1');

        $this->assertSame(0, (int) $batch->fresh()->qty);
        $this->assertSame(0, (int) $this->unit->fresh()->stok);

        $this->assertDatabaseHas('stock_movements', [
            'product_unit_id' => $this->unit->id,
            'product_batch_id' => $batch->id,
            'type' => StockMovementType::WriteOff->value,
            'quantity_delta' => -8,
            'quantity_before' => 8,
            'quantity_after' => 0,
            'created_by' => $this->owner->id,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/inventory/expiry-alerts')
            ->assertOk()
            ->assertJsonPath('expired_count', 0);
    }

    public function test_write_off_zero_qty_batch_returns_422(): void
    {
        $batch = ProductBatchFactory::new()->for($this->unit)->expired()->create(['qty' => 0]);

        $this->withToken($this->token)
            ->postJson("/api/product-batches/{$batch->id}/write-off")
            ->assertStatus(422);
    }

    public function test_staff_forbidden(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;
        $batch = ProductBatchFactory::new()->for($this->unit)->expired()->create(['qty' => 3]);

        $this->withToken($token)->getJson('/api/product-batches')->assertForbidden();
        $this->withToken($token)->postJson("/api/product-batches/{$batch->id}/write-off")->assertForbidden();
    }
}
