<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use Database\Factories\ProductBatchFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InputBoundsTest extends TestCase
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

    private function orderItems(int $count): array
    {
        return ProductUnit::factory()
            ->for($this->product)
            ->sequence(fn ($sequence) => ['satuan' => 'pcs-'.$sequence->index])
            ->count($count)
            ->create()
            ->map(fn (ProductUnit $unit) => [
                'product_unit_id' => $unit->id,
                'quantity' => 1,
            ])
            ->all();
    }

    private function opnameItems(int $count): array
    {
        return ProductUnit::factory()
            ->for($this->product)
            ->sequence(fn ($sequence) => ['satuan' => 'pcs-'.$sequence->index])
            ->count($count)
            ->create()
            ->map(fn (ProductUnit $unit) => [
                'product_unit_id' => $unit->id,
                'counted_qty' => 1,
            ])
            ->all();
    }

    public function test_order_store_rejects_more_than_100_items(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/orders', [
                'items' => $this->orderItems(101),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');
    }

    public function test_opname_rejects_more_than_200_items(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/inventory/opname', [
                'items' => $this->opnameItems(201),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');
    }

    public function test_expiry_alerts_only_include_batches_within_alert_window(): void
    {
        $unit = $this->product->units()->first();

        $withinWindow = ProductBatchFactory::new()->for($unit)->withBatchNo('B-SOON')->expiringIn(7)->create(['qty' => 4]);
        ProductBatchFactory::new()->for($unit)->withBatchNo('B-LATER')->expiringIn(90)->create(['qty' => 6]);

        $this->withToken($this->token)
            ->getJson('/api/inventory/expiry-alerts')
            ->assertOk()
            ->assertJsonPath('alert_days', 30)
            ->assertJsonPath('expired_count', 0)
            ->assertJsonPath('near_expiry_count', 1)
            ->assertJsonPath('near_expiry.0.id', $withinWindow->id)
            ->assertJsonPath('near_expiry.0.batch_no', 'B-SOON')
            ->assertJsonPath('near_expiry.0.qty', 4)
            ->assertJsonPath('near_expiry.0.days_left', 7);
    }
}
