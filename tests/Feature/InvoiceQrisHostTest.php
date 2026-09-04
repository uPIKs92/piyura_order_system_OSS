<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class InvoiceQrisHostTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_skips_http_fetch_when_mayar_qr_url_host_is_not_mayar(): void
    {
        $tenant = Tenant::factory()->create();
        TenantSettings::for($tenant->id)->setMayarApiKey('secret-key');
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $unit = $product->units()->first();
        $order = Order::factory()->create([
            'user_id' => $staff->id,
            'tenant_id' => $tenant->id,
            'status' => 'pending',
            'grand_total' => 50000,
            'subtotal' => 50000,
            'total_paid' => 0,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 50000,
            'quantity' => 1,
            'subtotal' => 50000,
        ]);

        $order->mayar_qr_url = 'https://evil.example/qr.png';
        $order->mayar_amount = 50000;
        $order->save();

        $order->refresh();
        $this->assertSame((float) $order->mayar_amount, (float) $order->remaining_amount);

        Http::fake();

        $this->withToken($token)
            ->get("/api/orders/{$order->id}/invoice/preview")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        Http::assertNothingSent();
    }

    public function test_preview_still_fetches_mayar_qr_from_allowed_host(): void
    {
        $tenant = Tenant::factory()->create();
        TenantSettings::for($tenant->id)->setMayarApiKey('secret-key');
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $unit = $product->units()->first();
        $order = Order::factory()->create([
            'user_id' => $staff->id,
            'tenant_id' => $tenant->id,
            'status' => 'pending',
            'grand_total' => 50000,
            'subtotal' => 50000,
            'total_paid' => 0,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 50000,
            'quantity' => 1,
            'subtotal' => 50000,
        ]);

        $order->mayar_qr_url = 'https://api.mayar.id/qr/ok.png';
        $order->mayar_amount = 50000;
        $order->save();

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        );

        Http::fake([
            'api.mayar.id/qr/ok.png' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);

        $captured = [];
        View::composer('pdf.invoice', function ($view) use (&$captured) {
            $captured = $view->getData();
        });

        $this->withToken($token)
            ->get("/api/orders/{$order->id}/invoice/preview")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringContainsString('data:image/png;base64,'.base64_encode($png), (string) ($captured['qrisDataUri'] ?? ''));
        Http::assertSent(fn ($request) => $request->url() === 'https://api.mayar.id/qr/ok.png');
    }

    public function test_repeated_previews_fetch_the_mayar_qr_png_only_once(): void
    {
        $tenant = Tenant::factory()->create();
        TenantSettings::for($tenant->id)->setMayarApiKey('secret-key');
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $unit = $product->units()->first();
        $order = Order::factory()->create([
            'user_id' => $staff->id,
            'tenant_id' => $tenant->id,
            'status' => 'pending',
            'grand_total' => 50000,
            'subtotal' => 50000,
            'total_paid' => 0,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 50000,
            'quantity' => 1,
            'subtotal' => 50000,
        ]);

        $order->mayar_qr_url = 'https://api.mayar.id/qr/ok.png';
        $order->mayar_amount = 50000;
        $order->save();

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        );

        $fetches = 0;

        Http::fake(function ($request) use (&$fetches, $png) {
            if (str_contains($request->url(), 'api.mayar.id/qr/ok.png')) {
                $fetches++;
            }

            return Http::response($png, 200, ['Content-Type' => 'image/png']);
        });

        $renders = [];
        View::composer('pdf.invoice', function ($view) use (&$renders) {
            $renders[] = $view->getData();
        });

        $this->withToken($token)
            ->get("/api/orders/{$order->id}/invoice/preview")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->withToken($token)
            ->get("/api/orders/{$order->id}/invoice/preview")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // The PNG body is cached ~5 minutes per URL, so the second
        // render still embeds the QR without a second Mayar fetch.
        $this->assertSame(1, $fetches);

        foreach ($renders as $render) {
            $this->assertStringContainsString(
                'data:image/png;base64,'.base64_encode($png),
                (string) ($render['qrisDataUri'] ?? '')
            );
        }
    }

    public function test_app_shell_json_blobs_hex_encode_script_breakout_in_tenant_name(): void
    {
        $malicious = '</script><script>alert(1)</script>';
        Tenant::factory()->create(['name' => $malicious]);

        $this->withoutVite();

        $html = view('app', ['cspNonce' => 'test-nonce'])->render();

        $this->assertStringNotContainsString($malicious, $html);
        $this->assertStringContainsString('\u003C', $html);
        $this->assertStringContainsString('\u003C\/script\u003E\u003Cscript\u003Ealert(1)\u003C\/script\u003E', $html);
    }
}
