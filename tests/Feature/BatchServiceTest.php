<?php

namespace Tests\Feature;

use App\Exceptions\ExpiredStockException;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Enums\StockMovementType;
use App\Models\User;
use Database\Factories\ProductBatchFactory;
use App\Services\BatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class BatchServiceTest extends TestCase
{
    use RefreshDatabase;

    private BatchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BatchService::class);
    }

    public function test_receive_creates_new_undated_batch(): void
    {
        $product = Product::factory()->create();
        $unit = $product->units()->first();

        $batch = DB::transaction(fn () => $this->service->receive($unit, 7));

        $this->assertSame($unit->id, $batch->product_unit_id);
        $this->assertSame($product->tenant_id, $batch->tenant_id);
        $this->assertNull($batch->batch_no);
        $this->assertNull($batch->expired_at);
        $this->assertSame(7, (int) $batch->qty);

        $this->assertDatabaseHas('product_batches', [
            'product_unit_id' => $unit->id,
            'batch_no' => null,
            'expired_at' => null,
            'qty' => 7,
        ]);
    }

    public function test_receive_merges_into_existing_batch_with_same_batch_no_and_expiry(): void
    {
        $product = Product::factory()->create();
        $unit = $product->units()->first();

        DB::transaction(fn () => $this->service->receive($unit, 5, '2026-09-01', 'B1'));

        $batch = DB::transaction(fn () => $this->service->receive($unit, 3, '2026-09-01', ' B1 '));

        $this->assertSame(8, (int) $batch->qty);
        $this->assertSame(1, ProductBatch::query()->where('product_unit_id', $unit->id)->count());
        $this->assertSame('2026-09-01', $batch->fresh()->expired_at->toDateString());
        $this->assertDatabaseHas('product_batches', [
            'product_unit_id' => $unit->id,
            'batch_no' => 'B1',
            'qty' => 8,
        ]);
    }

    public function test_receive_with_different_expiry_creates_separate_batch(): void
    {
        $product = Product::factory()->create();
        $unit = $product->units()->first();

        DB::transaction(fn () => $this->service->receive($unit, 5, '2026-09-01', 'B1'));
        DB::transaction(fn () => $this->service->receive($unit, 2, '2026-10-01', 'B1'));

        $batches = ProductBatch::query()->where('product_unit_id', $unit->id)->orderBy('id')->get();
        $this->assertSame(2, $batches->count());
        $this->assertSame('2026-09-01', $batches[0]->expired_at->toDateString());
        $this->assertSame(5, (int) $batches[0]->qty);
        $this->assertSame('2026-10-01', $batches[1]->expired_at->toDateString());
        $this->assertSame(2, (int) $batches[1]->qty);
    }

    public function test_allocate_fefo_picks_earliest_expiry_first_with_nulls_last(): void
    {
        $product = Product::factory()->create();
        $unit = $product->units()->first();

        $soonest = ProductBatchFactory::new()->expiringIn(5)->create(['product_unit_id' => $unit->id, 'qty' => 10]);
        $later = ProductBatchFactory::new()->expiringIn(30)->create(['product_unit_id' => $unit->id, 'qty' => 10]);
        $undated = ProductBatchFactory::new()->create(['product_unit_id' => $unit->id, 'qty' => 10]);

        $allocations = DB::transaction(fn () => $this->service->allocateFefo($unit, 15));

        $this->assertCount(2, $allocations);
        $this->assertSame($soonest->id, $allocations[0]['batch']->id);
        $this->assertSame(10, $allocations[0]['quantity']);
        $this->assertSame($later->id, $allocations[1]['batch']->id);
        $this->assertSame(5, $allocations[1]['quantity']);

        $this->assertSame(0, (int) $soonest->fresh()->qty);
        $this->assertSame(5, (int) $later->fresh()->qty);
        $this->assertSame(10, (int) $undated->fresh()->qty);
    }

    public function test_allocate_fefo_skips_expired_batches_and_throws(): void
    {
        $product = Product::factory()->create();
        $unit = $product->units()->first();

        ProductBatchFactory::new()->expired()->create(['product_unit_id' => $unit->id, 'qty' => 10]);
        ProductBatchFactory::new()->expiringIn(10)->create(['product_unit_id' => $unit->id, 'qty' => 5]);

        try {
            DB::transaction(fn () => $this->service->allocateFefo($unit, 7));
            $this->fail('ExpiredStockException was not thrown.');
        } catch (ExpiredStockException $e) {
            $this->assertSame($product->nama, $e->productName);
            $this->assertSame(5, $e->unexpiredAvailable);
            $this->assertSame(10, $e->expiredQty);
            $this->assertSame(7, $e->required);
        }

        $allocations = DB::transaction(fn () => $this->service->allocateFefo($unit, 5));

        $this->assertCount(1, $allocations);
        $this->assertSame(5, $allocations[0]['quantity']);
    }

    public function test_batch_expiring_today_is_still_sellable(): void
    {
        $product = Product::factory()->create();
        $unit = $product->units()->first();

        $today = ProductBatchFactory::new()->expiringIn(0)->create(['product_unit_id' => $unit->id, 'qty' => 10]);

        $allocations = DB::transaction(fn () => $this->service->allocateFefo($unit, 10));

        $this->assertCount(1, $allocations);
        $this->assertSame($today->id, $allocations[0]['batch']->id);
        $this->assertSame(0, (int) $today->fresh()->qty);
    }

    public function test_restore_puts_quantity_back_per_allocation(): void
    {
        $product = Product::factory()->create();
        $unit = $product->units()->first();

        $first = ProductBatchFactory::new()->expiringIn(5)->create(['product_unit_id' => $unit->id, 'qty' => 10]);
        $second = ProductBatchFactory::new()->expiringIn(30)->create(['product_unit_id' => $unit->id, 'qty' => 10]);

        $allocations = DB::transaction(function () use ($unit) {
            $allocations = $this->service->allocateFefo($unit, 15);
            $this->service->restore($allocations);

            return $allocations;
        });

        $this->assertSame(10, (int) $first->fresh()->qty);
        $this->assertSame(10, (int) $second->fresh()->qty);
    }

    public function test_allocate_splits_across_three_batches(): void
    {
        $product = Product::factory()->create();
        $unit = $product->units()->first();

        $soonest = ProductBatchFactory::new()->expiringIn(5)->create(['product_unit_id' => $unit->id, 'qty' => 10]);
        $middle = ProductBatchFactory::new()->expiringIn(30)->create(['product_unit_id' => $unit->id, 'qty' => 10]);
        $undated = ProductBatchFactory::new()->create(['product_unit_id' => $unit->id, 'qty' => 10]);

        $allocations = DB::transaction(fn () => $this->service->allocateFefo($unit, 25));

        $this->assertCount(3, $allocations);
        $this->assertSame($soonest->id, $allocations[0]['batch']->id);
        $this->assertSame(10, $allocations[0]['quantity']);
        $this->assertSame($middle->id, $allocations[1]['batch']->id);
        $this->assertSame(10, $allocations[1]['quantity']);
        $this->assertSame($undated->id, $allocations[2]['batch']->id);
        $this->assertSame(5, $allocations[2]['quantity']);

        $this->assertSame(0, (int) $soonest->fresh()->qty);
        $this->assertSame(0, (int) $middle->fresh()->qty);
        $this->assertSame(5, (int) $undated->fresh()->qty);
    }

    public function test_write_off_zeroes_batch_and_writes_movement(): void
    {
        $user = User::factory()->owner()->create();
        $product = Product::factory()->create();
        $unit = $product->units()->first();
        $unit->update(['stok' => 7]);
        $batch = ProductBatchFactory::new()->expired()->create(['product_unit_id' => $unit->id, 'qty' => 7]);

        $movement = $this->service->writeOff($batch, $user, 'Kedaluwarsa');

        $this->assertSame(0, (int) $batch->fresh()->qty);
        $this->assertSame(0, (int) $unit->fresh()->stok);
        $this->assertSame(StockMovementType::WriteOff, $movement->type);
        $this->assertSame(-7, $movement->quantity_delta);
        $this->assertSame(7, $movement->quantity_before);
        $this->assertSame(0, $movement->quantity_after);
        $this->assertSame($batch->id, $movement->product_batch_id);
        $this->assertSame('Kedaluwarsa', $movement->notes);

        $this->assertDatabaseHas('stock_movements', [
            'product_unit_id' => $unit->id,
            'product_batch_id' => $batch->id,
            'type' => StockMovementType::WriteOff->value,
            'quantity_delta' => -7,
            'reference_type' => $batch->getMorphClass(),
            'reference_id' => $batch->id,
            'created_by' => $user->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->writeOff($batch->fresh(), $user);
    }
}
