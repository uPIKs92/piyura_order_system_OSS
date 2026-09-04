<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use App\Services\OrderService;
use App\Support\TenantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OrderDeliveryFeeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private string $ownerToken;

    private User $staff;

    private string $staffToken;

    private Product $product;

    private ProductUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        $this->ownerToken = $this->owner->createToken('test')->plainTextToken;
        $this->staff = User::factory()->create();
        $this->staffToken = $this->staff->createToken('test')->plainTextToken;
        $this->product = Product::factory()->create();
        $this->unit = $this->product->units()->first();
        $this->unit->update(['stok' => 50, 'harga_jual' => 10000]);

        TenantSettings::for($this->staff->tenant_id)->updatePpn(true, 11.0);
    }

    public function test_per_km_fee_clamps_to_minimum(): void
    {
        $this->deliverySettings(['fee_mode' => 'per_km', 'fee_per_km' => 5000, 'min_fee' => 10000]);

        // 5000 x 1.5 = 7500 < min 10000 -> clamped.
        $order = $this->createOrderViaApi([
            'delivery_method' => 'diantar',
            'delivery_distance_km' => 1.5,
        ]);
        $this->assertSame('diantar', $order->delivery_method);
        $this->assertSame(10000.0, (float) $order->delivery_fee);
        $this->assertSame(1.5, (float) $order->delivery_distance_km);

        // 5000 x 3 = 15000 >= min -> rate wins.
        $order = $this->createOrderViaApi([
            'delivery_method' => 'diantar',
            'delivery_distance_km' => 3,
        ]);
        $this->assertSame(15000.0, (float) $order->delivery_fee);
    }

    public function test_fixed_mode_charges_flat_fee_regardless_of_distance(): void
    {
        $this->deliverySettings(['fee_mode' => 'fixed', 'fixed_fee' => 12000]);

        $order = $this->createOrderViaApi(['delivery_method' => 'diantar']);
        $this->assertSame(12000.0, (float) $order->delivery_fee);
        $this->assertNull($order->delivery_distance_km);

        $order = $this->createOrderViaApi([
            'delivery_method' => 'diantar',
            'delivery_distance_km' => 7,
        ]);
        $this->assertSame(12000.0, (float) $order->delivery_fee);
        $this->assertSame(7.0, (float) $order->delivery_distance_km);
    }

    public function test_explicit_override_fee_wins_over_resolved_fee(): void
    {
        $this->deliverySettings(['fee_mode' => 'per_km', 'fee_per_km' => 5000, 'min_fee' => 10000]);

        // Without the override this would clamp to 15000.
        $order = $this->createOrderViaApi([
            'delivery_method' => 'diantar',
            'delivery_distance_km' => 3,
            'delivery_fee' => 7000,
        ]);

        $this->assertSame(7000.0, (float) $order->delivery_fee);
    }

    public function test_diantar_without_distance_in_per_km_mode_has_no_fee(): void
    {
        $this->deliverySettings(['fee_mode' => 'per_km', 'fee_per_km' => 5000, 'min_fee' => 0]);

        $order = $this->createOrderViaApi(['delivery_method' => 'diantar']);

        $this->assertSame('diantar', $order->delivery_method);
        $this->assertSame(0.0, (float) $order->delivery_fee);
        $this->assertNull($order->delivery_distance_km);
        // Item 10000 + PPN 1100, fee 0.
        $this->assertSame(11100.0, (float) $order->grand_total);
    }

    public function test_diambil_zeros_fee_and_distance_on_draft_update(): void
    {
        $this->deliverySettings(['fee_mode' => 'per_km', 'fee_per_km' => 5000, 'min_fee' => 0]);

        $order = $this->createOrderViaApi([
            'delivery_method' => 'diantar',
            'delivery_distance_km' => 2,
        ]);
        $this->assertSame(10000.0, (float) $order->delivery_fee);

        $this->updateOrderViaApi($order, ['delivery_method' => 'diambil'])->assertOk();

        $fresh = $order->fresh();
        $this->assertSame('diambil', $fresh->delivery_method);
        $this->assertSame(0.0, (float) $fresh->delivery_fee);
        $this->assertNull($fresh->delivery_distance_km);
        // 10000 item + 1100 PPN, no fee.
        $this->assertSame(11100.0, (float) $fresh->grand_total);
    }

    public function test_grand_total_is_subtotal_minus_discount_plus_ppn_plus_fee(): void
    {
        $this->deliverySettings(['fee_mode' => 'per_km', 'fee_per_km' => 5000, 'min_fee' => 0]);

        // subtotal 20000, 10% line discount 2000, PPN 11% of 18000 = 1980, fee 5000.
        $order = $this->createOrderViaApi([
            'items' => [[
                'product_unit_id' => $this->unit->id,
                'quantity' => 2,
                'discount_type' => 'percent',
                'discount_value' => 10,
            ]],
            'delivery_method' => 'diantar',
            'delivery_distance_km' => 1,
        ]);

        $this->assertSame(20000.0, (float) $order->subtotal);
        $this->assertSame(2000.0, (float) $order->discount_total);
        $this->assertSame(5000.0, (float) $order->delivery_fee);
        $this->assertSame(24980.0, (float) $order->grand_total);

        // Bigger fee, identical items: PPN stays 1980 — fee is added after PPN.
        $bigger = $this->createOrderViaApi([
            'items' => [[
                'product_unit_id' => $this->unit->id,
                'quantity' => 2,
                'discount_type' => 'percent',
                'discount_value' => 10,
            ]],
            'delivery_method' => 'diantar',
            'delivery_fee' => 15000,
        ]);

        $this->assertSame(15000.0, (float) $bigger->delivery_fee);
        $this->assertSame(1980.0, (float) $bigger->ppn_amount);
        $this->assertSame(34980.0, (float) $bigger->grand_total);
    }

    public function test_draft_update_re_resolves_fee_from_current_settings(): void
    {
        $this->deliverySettings(['fee_mode' => 'per_km', 'fee_per_km' => 5000, 'min_fee' => 0]);

        $order = $this->createOrderViaApi([
            'delivery_method' => 'diantar',
            'delivery_distance_km' => 2,
        ]);
        $this->assertSame(10000.0, (float) $order->delivery_fee);

        // Owner raises the rate; stored fee survives a draft edit without
        // delivery fields in the payload.
        $this->deliverySettings(['fee_per_km' => 6000]);

        $this->updateOrderViaApi($order->fresh(), ['notes' => 'tanpa ongkir'])->assertOk();
        $this->assertSame(10000.0, (float) $order->fresh()->delivery_fee);

        // Re-sending delivery fields re-resolves with the new rate.
        $this->updateOrderViaApi($order->fresh(), [
            'delivery_method' => 'diantar',
            'delivery_distance_km' => 2,
        ])->assertOk();

        $fresh = $order->fresh();
        $this->assertSame(12000.0, (float) $fresh->delivery_fee);
        $this->assertSame(23100.0, (float) $fresh->grand_total);
    }

    public function test_non_draft_update_ignores_delivery_fields(): void
    {
        $this->deliverySettings(['fee_mode' => 'per_km', 'fee_per_km' => 5000, 'min_fee' => 0]);

        $order = $this->createOrderViaApi([
            'delivery_method' => 'diantar',
            'delivery_distance_km' => 2,
        ]);

        $this->updateOrderViaApi($order, ['status' => 'pending'])->assertOk();

        // Delivery fields in a non-draft payload are ignored wholesale.
        $this->updateOrderViaApi($order->fresh(), [
            'delivery_method' => 'diambil',
            'delivery_distance_km' => 5,
            'delivery_fee' => 999,
        ])->assertOk();

        $fresh = $order->fresh();
        $this->assertSame('diantar', $fresh->delivery_method);
        $this->assertSame(2.0, (float) $fresh->delivery_distance_km);
        $this->assertSame(10000.0, (float) $fresh->delivery_fee);
        // 10000 item + 1100 PPN + 10000 stored fee.
        $this->assertSame(21100.0, (float) $fresh->grand_total);
    }

    public function test_create_without_delivery_fields_defaults_to_diambil_zero_fee(): void
    {
        $order = $this->createOrderViaApi();

        $this->assertSame('diambil', $order->delivery_method);
        $this->assertSame(0.0, (float) $order->delivery_fee);
        $this->assertNull($order->delivery_distance_km);
        // Totals identical to the pre-feature math: 10000 + 1100 PPN.
        $this->assertSame(11100.0, (float) $order->grand_total);
    }

    public function test_legacy_orders_recalculate_identical_totals(): void
    {
        config(['ppn.enabled' => true, 'ppn.percentage' => 11.0]);

        // Pre-existing row without delivery fields: DB defaults apply.
        $order = Order::factory()->create(['status' => OrderStatus::Draft]);
        $order->items()->create([
            'product_id' => $this->product->id,
            'product_unit_id' => $this->unit->id,
            'product_name' => $this->product->nama,
            'satuan' => $this->unit->satuan,
            'price_snapshot' => 10000,
            'cost_snapshot' => 0,
            'quantity' => 2,
            'subtotal' => 20000,
        ]);

        app(OrderService::class)->recalculateTotals($order->fresh());

        $fresh = $order->fresh();
        $this->assertSame('diambil', $fresh->delivery_method);
        $this->assertSame(0.0, (float) $fresh->delivery_fee);
        $this->assertNull($fresh->delivery_distance_km);
        $this->assertSame(2200.0, (float) $fresh->ppn_amount);
        $this->assertSame(22200.0, (float) $fresh->grand_total);
    }

    public function test_delivery_validation_is_enforced(): void
    {
        $this->withToken($this->staffToken)->postJson('/api/orders', [
            'items' => [['product_unit_id' => $this->unit->id, 'quantity' => 1]],
            'delivery_method' => 'dijemput',
        ])->assertStatus(422)->assertJsonValidationErrors('delivery_method');

        $this->withToken($this->staffToken)->postJson('/api/orders', [
            'items' => [['product_unit_id' => $this->unit->id, 'quantity' => 1]],
            'delivery_method' => 'diantar',
            'delivery_distance_km' => 1000,
        ])->assertStatus(422)->assertJsonValidationErrors('delivery_distance_km');

        $this->withToken($this->staffToken)->postJson('/api/orders', [
            'items' => [['product_unit_id' => $this->unit->id, 'quantity' => 1]],
            'delivery_method' => 'diantar',
            'delivery_distance_km' => -1,
        ])->assertStatus(422)->assertJsonValidationErrors('delivery_distance_km');

        $this->withToken($this->staffToken)->postJson('/api/orders', [
            'items' => [['product_unit_id' => $this->unit->id, 'quantity' => 1]],
            'delivery_method' => 'diantar',
            'delivery_fee' => -1,
        ])->assertStatus(422)->assertJsonValidationErrors('delivery_fee');

        $order = $this->createOrderViaApi();
        $this->updateOrderViaApi($order, ['delivery_method' => 'kurir'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('delivery_method');
    }

    private function deliverySettings(array $attributes): void
    {
        TenantSettings::for($this->staff->tenant_id)->updateDelivery($attributes);
    }

    private function createOrderViaApi(array $payload = []): Order
    {
        $response = $this->withToken($this->staffToken)
            ->postJson('/api/orders', array_merge([
                'items' => [['product_unit_id' => $this->unit->id, 'quantity' => 1]],
            ], $payload))
            ->assertCreated();

        return Order::whereKey($response->json('id'))->first();
    }

    private function updateOrderViaApi(Order $order, array $payload): TestResponse
    {
        return $this->withToken($this->staffToken)
            ->putJson("/api/orders/{$order->id}", array_merge([
                'version' => $order->version,
            ], $payload));
    }
}
