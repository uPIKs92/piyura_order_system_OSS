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
        $this->assertEquals(20000, (float) $this->order->change_due);
        $this->assertEquals(20000, (float) $this->order->change_amount);
    }

    public function test_overpay_records_change_on_payment(): void
    {
        $this->withToken($this->staffToken)->postJson("/api/orders/{$this->order->id}/payments", [
            'amount' => 120000,
            'metode' => 'cash',
        ])->assertCreated();

        $payment = $this->order->fresh()->payments->first();
        $this->assertEquals(120000, (float) $payment->amount);
        $this->assertEquals(120000, (float) $payment->tendered);
        $this->assertEquals(20000, (float) $payment->change_amount);
    }

    public function test_tendered_overpay_records_change_without_overpaying(): void
    {
        // Cashier flow: Jumlah stays at the remaining balance while the
        // buyer hands over more cash via Uang Diterima. The change must be
        // computed from tendered, and the order must not become overpaid.
        $this->withToken($this->staffToken)->postJson("/api/orders/{$this->order->id}/payments", [
            'amount' => 100000,
            'tendered' => 120000,
            'metode' => 'cash',
        ])->assertCreated();

        $payment = $this->order->fresh()->payments->first();
        $this->assertEquals(100000, (float) $payment->amount);
        $this->assertEquals(120000, (float) $payment->tendered);
        $this->assertEquals(20000, (float) $payment->change_amount);

        $this->order->refresh();
        $this->assertEquals(100000, (float) $this->order->total_paid);
        $this->assertEquals('paid', $this->order->paymentStatus());
        $this->assertEquals(20000, (float) $this->order->change_due);
        $this->assertEquals(20000, (float) $this->order->change_amount);
    }

    public function test_underpay_keeps_remaining_amount(): void
    {
        $this->withToken($this->staffToken)->postJson("/api/orders/{$this->order->id}/payments", [
            'amount' => 30000,
            'metode' => 'cash',
        ])->assertCreated();

        $this->order->refresh();
        $this->assertEquals('partial', $this->order->payment_status);
        $this->assertEquals(70000, (float) $this->order->remaining_amount);
        $this->assertEquals(0, (float) $this->order->change_due);
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

    public function test_deleting_payment_recalculates_total_paid(): void
    {
        $this->withToken($this->staffToken)->postJson("/api/orders/{$this->order->id}/payments", [
            'amount' => 50000, 'metode' => 'cash',
        ])->assertCreated();
        $this->withToken($this->staffToken)->postJson("/api/orders/{$this->order->id}/payments", [
            'amount' => 50000, 'metode' => 'transfer',
        ])->assertCreated();

        $this->order->refresh();
        $this->assertEquals(100000, (float) $this->order->total_paid);

        $payment = $this->order->fresh()->payments->first();

        $this->actingAs(User::factory()->owner()->create(), 'sanctum')
            ->deleteJson("/api/orders/{$this->order->id}/payments/{$payment->id}")
            ->assertOk();

        $this->order->refresh();
        $this->assertEquals(50000, (float) $this->order->total_paid);
        $this->assertEquals('partial', $this->order->payment_status);
    }

    public function test_staff_cannot_delete_payment(): void
    {
        $this->withToken($this->staffToken)->postJson("/api/orders/{$this->order->id}/payments", [
            'amount' => 50000, 'metode' => 'cash',
        ])->assertCreated();

        $payment = $this->order->fresh()->payments->first();

        $this->withToken($this->staffToken)
            ->deleteJson("/api/orders/{$this->order->id}/payments/{$payment->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    public function test_fractional_payments_sum_to_exact_total_paid(): void
    {
        $order = $this->orderWithGrandTotal(75000.15);

        $this->withToken($this->staffToken)->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 50000.05, 'metode' => 'cash',
        ])->assertCreated();
        $this->withToken($this->staffToken)->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 25000.10, 'metode' => 'transfer',
        ])->assertCreated();

        // 5000005c + 2500010c = 7500015c exactly. The second payment exactly
        // covers the remaining 7500015c - 5000005c = 2500010c, so no change.
        $order->refresh();
        $this->assertSame(75000.15, (float) $order->total_paid);
        $this->assertSame(0.0, (float) $order->change_due);
        $this->assertSame('paid', $order->paymentStatus());
    }

    public function test_overpay_change_is_cent_exact(): void
    {
        $order = $this->orderWithGrandTotal(10000.05);

        $this->withToken($this->staffToken)->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 25000.10, 'metode' => 'cash',
        ])->assertCreated();

        // change = 2500010c - 1000005c = 1500005c (15000.05)
        $payment = $order->fresh()->payments->first();
        $this->assertSame(15000.05, (float) $payment->change_amount);
        $this->assertSame(15000.05, (float) $order->fresh()->change_due);
    }

    public function test_deleting_fractional_payment_recalculates_exact_total_paid(): void
    {
        $order = $this->orderWithGrandTotal(75000.15);

        $this->withToken($this->staffToken)->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 50000.05, 'metode' => 'cash',
        ])->assertCreated();
        $this->withToken($this->staffToken)->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 25000.10, 'metode' => 'transfer',
        ])->assertCreated();

        $first = $order->fresh()->payments()->oldest('id')->first();

        $this->actingAs(User::factory()->owner()->create(), 'sanctum')
            ->deleteJson("/api/orders/{$order->id}/payments/{$first->id}")
            ->assertOk();

        // 7500015c - 5000005c = 2500010c exactly.
        $order->refresh();
        $this->assertSame(25000.10, (float) $order->total_paid);
        $this->assertSame('partial', $order->payment_status);
    }

    private function orderWithGrandTotal(float $grandTotal): Order
    {
        $product = Product::factory()->create();
        $unit = $product->units()->first();
        $order = Order::factory()->create([
            'user_id' => $this->staff->id,
            'status' => OrderStatus::Pending,
            'grand_total' => $grandTotal,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => $grandTotal,
            'quantity' => 1,
            'subtotal' => $grandTotal,
        ]);

        return $order;
    }
}
