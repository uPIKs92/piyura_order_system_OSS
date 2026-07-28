<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private string $staffToken;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->create();
        $this->staffToken = $this->staff->createToken('test')->plainTextToken;
        $product = Product::factory()->create();
        $unit = $product->units()->first();
        $this->order = Order::factory()->create([
            'user_id' => $this->staff->id,
            'status' => OrderStatus::Pending,
            'grand_total' => 100000,
        ]);
        $this->order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 100000,
            'quantity' => 1,
            'subtotal' => 100000,
        ]);
    }

    public function test_can_add_payment(): void
    {
        $this->withToken($this->staffToken)->postJson("/api/orders/{$this->order->id}/payments", [
            'amount' => 50000,
            'metode' => 'cash',
        ])->assertCreated();

        $this->assertEquals(50000, $this->order->fresh()->total_paid);
    }

    public function test_multiple_payments(): void
    {
        $this->withToken($this->staffToken)->postJson("/api/orders/{$this->order->id}/payments", [
            'amount' => 50000, 'metode' => 'cash',
        ]);
        $this->withToken($this->staffToken)->postJson("/api/orders/{$this->order->id}/payments", [
            'amount' => 50000, 'metode' => 'transfer',
        ])->assertCreated();

        $this->order->refresh();
        $this->assertEquals('paid', $this->order->paymentStatus());
    }

    public function test_overpaid_payment_status(): void
    {
        $this->withToken($this->staffToken)->postJson("/api/orders/{$this->order->id}/payments", [
            'amount' => 120000,
            'metode' => 'cash',
        ])->assertCreated();

        $this->order->refresh();
        $this->assertEquals('overpaid', $this->order->payment_status);
        $this->assertEquals(0, $this->order->remaining_amount);
    }

    public function test_order_json_includes_payment_fields(): void
    {
        $this->withToken($this->staffToken)->postJson("/api/orders/{$this->order->id}/payments", [
            'amount' => 30000,
            'metode' => 'cash',
        ]);

        $response = $this->withToken($this->staffToken)->getJson("/api/orders/{$this->order->id}");

        $response->assertOk()
            ->assertJsonPath('payment_status', 'partial')
            ->assertJsonPath('remaining_amount', 70000);
    }
}
