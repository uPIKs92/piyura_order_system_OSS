<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mayar webhook receiver: tenant resolved from URL slug, order matched by
 * amount, replay-safe via external_reference. These requests are unauthenticated
 * (like real webhook traffic) so they are unaffected by the SPA CSRF layer.
 */
class MayarWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        // Webhooks are external (Mayar server → us), never a stateful SPA request,
        // so they legitimately carry no CSRF token. Exempt the CSRF validator for
        // these tests (the local test env marks 127.0.0.1 as a Sanctum stateful
        // domain, which would otherwise force CSRF on every API POST).
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        // Trust the test runner IP so the webhook IP allowlist passes; individual
        // tests override this to exercise rejection paths.
        config(['services.mayar.webhook_ip_allowlist' => '127.0.0.1']);

        $this->tenant = Tenant::factory()->create(['slug' => 'toko-demo']);
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $unit = $product->units()->first();

        $this->order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'status' => OrderStatus::Pending,
            'grand_total' => 75000,
            'total_paid' => 0,
            'mayar_qr_url' => 'https://api.mayar.id/qr/example.png',
            'mayar_amount' => 75000,
        ]);
        $this->order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 75000,
            'quantity' => 1,
            'subtotal' => 75000,
        ]);
    }

    public function test_webhook_records_payment_for_matching_order(): void
    {
        $this->postJson('/api/webhooks/mayar/toko-demo', [
            'event' => 'payment.received',
            'data' => ['id' => 'mayar-tx-1', 'amount' => 75000],
        ])->assertOk()->assertJson(['message' => 'Payment recorded.']);

        $this->order->refresh();
        $this->assertSame('75000.00', (string) $this->order->total_paid);
        $this->assertSame('paid', $this->order->payment_status);

        $this->assertDatabaseHas('payments', [
            'order_id' => $this->order->id,
            'external_reference' => 'mayar-tx-1',
            'metode' => 'qris',
        ]);
    }

    public function test_webhook_is_idempotent_on_replay(): void
    {
        $payload = ['data' => ['id' => 'mayar-tx-2', 'amount' => 75000]];

        $this->postJson('/api/webhooks/mayar/toko-demo', $payload)->assertOk();
        $this->postJson('/api/webhooks/mayar/toko-demo', $payload)
            ->assertOk()
            ->assertJson(['message' => 'Duplicate webhook ignored.']);

        $this->assertSame(1, Payment::where('external_reference', 'mayar-tx-2')->count());
        $this->assertSame('75000.00', (string) $this->order->fresh()->total_paid);
    }

    public function test_webhook_ignores_when_no_matching_order(): void
    {
        $this->postJson('/api/webhooks/mayar/toko-demo', [
            'data' => ['id' => 'mayar-tx-3', 'amount' => 99999],
        ])->assertOk()->assertJson(['message' => 'No matching order.']);

        $this->assertSame('0.00', (string) $this->order->fresh()->total_paid);
    }

    public function test_webhook_rejects_non_allowlisted_ip(): void
    {
        config(['services.mayar.webhook_ip_allowlist' => '203.0.113.10']);

        $this->postJson('/api/webhooks/mayar/toko-demo', [
            'data' => ['id' => 'mayar-tx-4', 'amount' => 75000],
        ])->assertForbidden();
    }

    public function test_webhook_unknown_tenant_returns_404(): void
    {
        $this->postJson('/api/webhooks/mayar/no-such-tenant', [
            'data' => ['id' => 'x', 'amount' => 100],
        ])->assertNotFound();
    }

    public function test_webhook_rejects_when_ip_allowlist_not_configured(): void
    {
        config(['services.mayar.webhook_ip_allowlist' => '']);

        $this->postJson('/api/webhooks/mayar/toko-demo', [
            'data' => ['id' => 'mayar-tx-empty', 'amount' => 75000],
        ])->assertForbidden();
    }

    public function test_webhook_rejects_ambiguous_amount_match(): void
    {
        // A second pending order at the same amount makes the match ambiguous.
        Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->order->user_id,
            'status' => OrderStatus::Pending,
            'grand_total' => 75000,
            'total_paid' => 0,
            'mayar_qr_url' => 'https://api.mayar.id/qr/other.png',
            'mayar_amount' => 75000,
        ]);

        $this->postJson('/api/webhooks/mayar/toko-demo', [
            'data' => ['id' => 'mayar-tx-ambiguous', 'amount' => 75000],
        ])->assertStatus(422)->assertJson(['message' => 'Ambiguous match — manual review required.']);

        $this->assertSame('0.00', (string) $this->order->fresh()->total_paid);
        $this->assertSame(0, Payment::where('external_reference', 'mayar-tx-ambiguous')->count());
    }

    public function test_webhook_duplicate_reference_race_returns_duplicate_ignored(): void
    {
        $this->instance(PaymentService::class, new class extends PaymentService
        {
            public function addPayment(Order $order, ?User $user, array $data): Payment
            {
                Payment::create([
                    'order_id' => $order->id,
                    'amount' => 75000,
                    'metode' => PaymentMethod::Qris,
                    'paid_at' => now(),
                    'external_reference' => $data['external_reference'],
                ]);

                return parent::addPayment($order, $user, $data);
            }
        });

        $this->postJson('/api/webhooks/mayar/toko-demo', [
            'data' => ['id' => 'mayar-tx-race', 'amount' => 75000],
        ])->assertOk()->assertJson(['message' => 'Duplicate webhook ignored.']);

        $this->assertSame(1, Payment::where('external_reference', 'mayar-tx-race')->count());
    }

    public function test_external_reference_is_enforced_unique_at_database_level(): void
    {
        $service = $this->app->make(PaymentService::class);

        $service->addPayment($this->order, null, [
            'amount' => 1000,
            'metode' => 'qris',
            'external_reference' => 'mayar-tx-dup',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        $service->addPayment($this->order, null, [
            'amount' => 1000,
            'metode' => 'qris',
            'external_reference' => 'mayar-tx-dup',
        ]);
    }
}
