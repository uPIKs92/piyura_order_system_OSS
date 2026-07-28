<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_download_invoice_pdf(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;
        $product = Product::factory()->create(['tenant_id' => $staff->tenant_id]);
        $unit = $product->units()->first();
        $order = Order::factory()->create(['user_id' => $staff->id, 'tenant_id' => $staff->tenant_id]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 10000,
            'quantity' => 1,
            'subtotal' => 10000,
        ]);

        $this->withToken($token)
            ->get("/api/orders/{$order->id}/invoice")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_invoice_pdf_contains_tenant_branding_not_hardcoded_name(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'TOKO KUSTOM',
            'tagline' => 'Tagline Kustom',
            'invoice_footer_text' => 'Pesan footer kustom',
        ]);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);

        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $unit = $product->units()->first();
        $order = Order::factory()->create(['user_id' => $staff->id, 'tenant_id' => $tenant->id]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 10000,
            'quantity' => 1,
            'subtotal' => 10000,
        ]);

        $html = view('pdf.invoice', [
            'order' => $order->load(['items.productUnit', 'tenant']),
            'tenant' => $tenant,
            'logoDataUri' => null,
            'showPlatformCredit' => true,
            'platformName' => 'Piyuralabs',
            'appName' => 'Order Tracker',
        ])->render();

        $this->assertStringContainsString('TOKO KUSTOM', $html);
        $this->assertStringContainsString('Tagline Kustom', $html);
        $this->assertStringContainsString('Pesan footer kustom', $html);
        $this->assertStringContainsString('Powered by Piyuralabs · Order Tracker', $html);
        $this->assertStringNotContainsString('MAMARY MART 88', $html);
    }
}
