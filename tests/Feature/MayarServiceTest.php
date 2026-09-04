<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MayarService;
use App\Support\TenantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * MayarService logic tested with a fake HTTP layer (no live Mayar calls, no
 * HTTP routing, so unaffected by the SPA CSRF layer that blocks token POSTs
 * in this environment).
 */
class MayarServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_qr_code_returns_url_and_amount(): void
    {
        $tenant = $this->tenantWithApiKey('secret-key');

        Http::fake([
            'api.mayar.id/hl/v2/qr-codes/create' => Http::response([
                'data' => ['url' => 'https://api.mayar.id/qr/abc.png', 'amount' => 50000],
            ], 200),
        ]);

        $qr = MayarService::forTenant($tenant->id)->createQrCode(50000);

        $this->assertSame('https://api.mayar.id/qr/abc.png', $qr['url']);
        $this->assertSame(50000.0, $qr['amount']);
    }

    public function test_qr_code_for_order_caches_url_and_amount_on_order(): void
    {
        $tenant = $this->tenantWithApiKey('secret-key');
        $order = $this->pendingOrder($tenant, 120000);

        Http::fake([
            'api.mayar.id/hl/v2/qr-codes/create' => Http::response([
                'data' => ['url' => 'https://api.mayar.id/qr/cached.png', 'amount' => 120000],
            ], 200),
        ]);

        $service = MayarService::forTenant($tenant->id);

        $first = $service->qrCodeForOrder($order);
        $this->assertSame('https://api.mayar.id/qr/cached.png', $first['url']);

        $order->refresh();
        $this->assertSame('https://api.mayar.id/qr/cached.png', $order->mayar_qr_url);
        $this->assertSame('120000.00', (string) $order->mayar_amount);

        // Second call must reuse cache and NOT hit the API again.
        Http::fake([
            'api.mayar.id/hl/v2/qr-codes/create' => Http::response(
                ['data' => ['url' => 'https://api.mayar.id/qr/SURPRISE.png']], 200
            ),
        ]);

        $cached = $service->qrCodeForOrder($order->fresh());
        $this->assertSame('https://api.mayar.id/qr/cached.png', $cached['url']);
    }

    public function test_create_qr_code_throws_when_api_key_missing(): void
    {
        $tenant = Tenant::factory()->create();

        $this->expectException(\RuntimeException::class);
        MayarService::forTenant($tenant->id)->createQrCode(1000);
    }

    public function test_create_qr_code_throws_on_api_failure(): void
    {
        $tenant = $this->tenantWithApiKey('bad-key');

        Http::fake([
            'api.mayar.id/hl/v2/qr-codes/create' => Http::response(['message' => 'unauthorized'], 401),
        ]);

        $this->expectException(\RuntimeException::class);
        MayarService::forTenant($tenant->id)->createQrCode(1000);
    }

    public function test_health_check_returns_false_without_key(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertFalse(MayarService::forTenant($tenant->id)->healthCheck());
    }

    private function tenantWithApiKey(string $key): Tenant
    {
        $tenant = Tenant::factory()->create();
        TenantSettings::for($tenant->id)->setMayarApiKey($key);

        return $tenant;
    }

    private function pendingOrder(Tenant $tenant, int $grandTotal): Order
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $unit = $product->units()->first();

        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'grand_total' => $grandTotal,
            'total_paid' => 0,
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
