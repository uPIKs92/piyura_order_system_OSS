<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffCostVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private string $ownerToken;

    private User $staff;

    private string $staffToken;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
        $this->ownerToken = $this->owner->createToken('test')->plainTextToken;
        $this->staff = User::factory()->create();
        $this->staffToken = $this->staff->createToken('test')->plainTextToken;
        $this->product = Product::factory()->create();
        $this->product->units()->first()->update([
            'harga_jual' => 25000,
            'harga_beli' => 15000,
            'stok' => 100,
        ]);
    }

    public function test_owner_sees_harga_beli_on_product_index_and_show(): void
    {
        $this->withToken($this->ownerToken)
            ->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.units.0.harga_beli', '15000.00');

        $this->withToken($this->ownerToken)
            ->getJson('/api/products/'.$this->product->id)
            ->assertOk()
            ->assertJsonPath('units.0.harga_beli', '15000.00');
    }

    public function test_staff_does_not_see_harga_beli_on_product_index_and_show(): void
    {
        $this->withToken($this->staffToken)
            ->getJson('/api/products')
            ->assertOk()
            ->assertJsonMissingPath('data.0.units.0.harga_beli')
            ->assertJsonPath('data.0.units.0.harga_jual', '25000.00')
            ->assertJsonPath('data.0.units.0.satuan', 'pcs');

        $this->withToken($this->staffToken)
            ->getJson('/api/products/'.$this->product->id)
            ->assertOk()
            ->assertJsonMissingPath('units.0.harga_beli')
            ->assertJsonPath('units.0.harga_jual', '25000.00')
            ->assertJsonPath('nama', $this->product->nama);
    }

    public function test_staff_order_responses_hide_cost_snapshot(): void
    {
        $unit = $this->product->units()->first();

        $create = $this->withToken($this->staffToken)->postJson('/api/orders', [
            'customer_name' => 'Budi',
            'order_date' => now()->toDateString(),
            'items' => [['product_unit_id' => $unit->id, 'quantity' => 2]],
        ]);

        $create->assertCreated()
            ->assertJsonMissingPath('items.0.cost_snapshot')
            ->assertJsonPath('items.0.price_snapshot', '25000.00')
            ->assertJsonPath('items.0.product_name', $this->product->nama);

        $orderId = $create->json('id');

        $this->withToken($this->staffToken)
            ->getJson('/api/orders/'.$orderId)
            ->assertOk()
            ->assertJsonMissingPath('items.0.cost_snapshot')
            ->assertJsonMissingPath('items.0.product_unit.harga_beli')
            ->assertJsonPath('items.0.price_snapshot', '25000.00')
            ->assertJsonPath('items.0.product_unit.satuan', 'pcs');

        $this->withToken($this->staffToken)
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonMissingPath('data.0.items.0.cost_snapshot')
            ->assertJsonPath('data.0.items.0.product_name', $this->product->nama);

        $update = $this->withToken($this->staffToken)->patchJson('/api/orders/'.$orderId, [
            'version' => 1,
            'notes' => 'updated',
        ]);

        $update->assertOk()
            ->assertJsonMissingPath('items.0.cost_snapshot')
            ->assertJsonPath('items.0.price_snapshot', '25000.00');
    }

    public function test_owner_sees_cost_snapshot_on_order_show(): void
    {
        $unit = $this->product->units()->first();

        $orderId = $this->withToken($this->staffToken)->postJson('/api/orders', [
            'customer_name' => 'Budi',
            'order_date' => now()->toDateString(),
            'items' => [['product_unit_id' => $unit->id, 'quantity' => 2]],
        ])->assertCreated()->json('id');

        $this->app['auth']->forgetGuards();

        $this->withToken($this->ownerToken)
            ->getJson('/api/orders/'.$orderId)
            ->assertOk()
            ->assertJsonPath('items.0.cost_snapshot', '15000.00')
            ->assertJsonPath('items.0.product_unit.harga_beli', '15000.00');
    }

    public function test_staff_activity_properties_strip_cost_snapshot_while_owner_keeps_it(): void
    {
        $unit = $this->product->units()->first();

        $orderId = $this->withToken($this->staffToken)->postJson('/api/orders', [
            'customer_name' => 'Budi',
            'order_date' => now()->toDateString(),
            'items' => [['product_unit_id' => $unit->id, 'quantity' => 1]],
        ])->assertCreated()->json('id');

        $unit->update(['harga_jual' => 30000, 'harga_beli' => 18000]);

        $this->withToken($this->staffToken)->patchJson('/api/orders/'.$orderId, [
            'version' => 1,
            'status' => 'pending',
        ])->assertOk();

        $this->app['auth']->forgetGuards();

        $ownerLogs = $this->withToken($this->ownerToken)
            ->getJson('/api/orders/'.$orderId.'/activity')
            ->assertOk()
            ->json();

        $ownerEntry = collect($ownerLogs)->firstWhere('description', 'price_changed');
        $this->assertNotNull($ownerEntry);
        $this->assertEquals(15000, $ownerEntry['properties']['old']['cost_snapshot']);
        $this->assertEquals(18000, $ownerEntry['properties']['attributes']['cost_snapshot']);
        $this->assertEquals(25000, $ownerEntry['properties']['old']['price_snapshot']);
        $this->assertEquals(30000, $ownerEntry['properties']['attributes']['price_snapshot']);

        $this->app['auth']->forgetGuards();

        $staffLogs = $this->withToken($this->staffToken)
            ->getJson('/api/orders/'.$orderId.'/activity')
            ->assertOk()
            ->json();

        $staffEntry = collect($staffLogs)->firstWhere('description', 'price_changed');
        $this->assertNotNull($staffEntry);
        $this->assertArrayNotHasKey('cost_snapshot', $staffEntry['properties']['old']);
        $this->assertArrayNotHasKey('cost_snapshot', $staffEntry['properties']['attributes']);
        $this->assertEquals(25000, $staffEntry['properties']['old']['price_snapshot']);
        $this->assertEquals(30000, $staffEntry['properties']['attributes']['price_snapshot']);
        $this->assertTrue($staffEntry['properties']['important']);
        $this->assertSame($this->product->nama, $staffEntry['properties']['product_name']);

        $this->assertTrue(
            collect($staffLogs)->pluck('description')->contains('status_changed'),
        );
    }
}
