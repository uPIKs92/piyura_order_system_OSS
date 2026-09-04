<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItemBatch;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Support\TenantSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Factories\ProductBatchFactory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
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
            'appName' => 'Simple Order Systems',
        ])->render();

        $this->assertStringContainsString('TOKO KUSTOM', $html);
        $this->assertStringContainsString('Tagline Kustom', $html);
        $this->assertStringContainsString('Pesan footer kustom', $html);
        $this->assertStringContainsString('Powered by Piyuralabs · Simple Order Systems', $html);
        $this->assertStringNotContainsString('MAMARY MART 88', $html);
    }

    public function test_invoice_pdf_shows_batch_no_and_expiry_per_line_item(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;
        $product = Product::factory()->create(['tenant_id' => $staff->tenant_id]);
        $unit = $product->units()->first();
        $order = Order::factory()->create(['user_id' => $staff->id, 'tenant_id' => $staff->tenant_id]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 10000,
            'quantity' => 2,
            'subtotal' => 20000,
        ]);

        $datedBatch = ProductBatchFactory::new()
            ->withBatchNo('B-123')
            ->expiringIn(30)
            ->create(['product_unit_id' => $unit->id]);
        $undatedBatch = ProductBatchFactory::new()->create(['product_unit_id' => $unit->id]);

        OrderItemBatch::create(['order_item_id' => $item->id, 'product_batch_id' => $datedBatch->id, 'quantity' => 1]);
        OrderItemBatch::create(['order_item_id' => $item->id, 'product_batch_id' => $undatedBatch->id, 'quantity' => 1]);

        $this->withToken($token)
            ->get("/api/orders/{$order->id}/invoice")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $html = view('pdf.invoice', [
            'order' => $order->load(['items.productUnit', 'items.batchAllocations.productBatch', 'tenant']),
            'tenant' => $order->tenant,
            'logoDataUri' => null,
            'showPlatformCredit' => false,
            'platformName' => 'Piyuralabs',
            'appName' => 'Simple Order Systems',
        ])->render();

        $this->assertStringContainsString('Batch B-123', $html);
        $this->assertStringContainsString('Exp '.today()->addDays(30)->format('d-m-Y'), $html);
    }

    public function test_invoice_pdf_renders_without_batch_allocations(): void
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

        $html = view('pdf.invoice', [
            'order' => $order->load(['items.productUnit', 'items.batchAllocations.productBatch', 'tenant']),
            'tenant' => $order->tenant,
            'logoDataUri' => null,
            'showPlatformCredit' => false,
            'platformName' => 'Piyuralabs',
            'appName' => 'Simple Order Systems',
        ])->render();

        $this->assertStringNotContainsString('Batch ', $html);
    }
    public function test_invoice_shows_bank_transfer_section_when_configured_even_without_payments(): void
    {
        $tenant = Tenant::factory()->create();
        TenantSettings::for($tenant->id)->updatePayment([
            'bank_name' => 'Bank BCA',
            'bank_account_name' => 'Toko Kustom',
            'bank_account_number' => '1234567890',
        ]);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;
        $order = $this->orderWithItem($staff);

        $this->assertSame(0, $order->payments()->count());

        $html = $this->invoiceHtml($token, $order);

        $this->assertStringContainsString('Pembayaran', $html);
        $this->assertStringContainsString('Transfer', $html);
        $this->assertStringNotContainsString('Pembayaran Transfer', $html);
        $this->assertStringContainsString('Bank BCA', $html);
        $this->assertStringContainsString('1234567890', $html);
    }

    public function test_invoice_embeds_static_qris_image_as_data_uri_with_zero_payments(): void
    {
        Storage::fake('public');
        $tenant = Tenant::factory()->create();
        $path = "tenants/{$tenant->id}/qris.png";
        Storage::disk('public')->put($path, $this->qrPng());
        TenantSettings::for($tenant->id)->setQrisImage($path);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;
        $order = $this->orderWithItem($staff);

        $this->assertSame(0, $order->payments()->count());

        $html = $this->invoiceHtml($token, $order);

        $this->assertStringContainsString('Pembayaran', $html);
        $this->assertStringContainsString('QRIS', $html);
        $this->assertStringNotContainsString('Pembayaran QRIS', $html);
        $this->assertStringContainsString('data:image/', $html);
        $this->assertStringContainsString('data:image/png;base64,'.base64_encode($this->qrPng()), $html);
        $this->assertStringContainsString('Scan untuk membayar', $html);
    }

    public function test_invoice_payment_card_shows_transfer_and_qris_together_when_both_configured(): void
    {
        Storage::fake('public');
        $tenant = Tenant::factory()->create();
        TenantSettings::for($tenant->id)->updatePayment([
            'bank_name' => 'Bank BCA',
            'bank_account_name' => 'Toko Kustom',
            'bank_account_number' => '1234567890',
        ]);
        $path = "tenants/{$tenant->id}/qris.png";
        Storage::disk('public')->put($path, $this->qrPng());
        TenantSettings::for($tenant->id)->setQrisImage($path);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;
        $order = $this->orderWithItem($staff);

        $html = $this->invoiceHtml($token, $order);

        $this->assertSame(1, substr_count($html, '>Pembayaran</p>'));
        $this->assertStringContainsString('Transfer', $html);
        $this->assertStringContainsString('Bank BCA', $html);
        $this->assertStringContainsString('1234567890', $html);
        $this->assertStringContainsString('QRIS', $html);
        $this->assertStringContainsString('data:image/png;base64,'.base64_encode($this->qrPng()), $html);
        $this->assertStringContainsString('Scan untuk membayar', $html);
        $this->assertStringNotContainsString('Pembayaran Transfer', $html);
        $this->assertStringNotContainsString('Pembayaran QRIS', $html);
    }

    public function test_invoice_shows_tunai_and_kembalian_for_cash_overpay(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        $order = $this->orderWithItem($staff, ['grand_total' => 50000, 'subtotal' => 50000]);

        // Cashier flow: Jumlah = remaining (50000), buyer hands 80000.
        $this->withToken($token)->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 50000,
            'tendered' => 80000,
            'metode' => 'cash',
        ])->assertCreated();

        $html = $this->invoiceHtml($token, $order->fresh());

        // The buyer's handed-over money (80.000) must print next to
        // Kembalian — not the credited amount (50.000) as "Dibayar",
        // which reads like the buyer paid less than they handed over.
        $this->assertStringContainsString('Tunai', $html);
        $this->assertStringContainsString('IDR 80.000', $html);
        $this->assertStringContainsString('Kembalian', $html);
        $this->assertStringContainsString('IDR 30.000', $html);
        $this->assertStringNotContainsString('Dibayar', $html);
    }

    public function test_invoice_shows_lunas_stamp_only_when_fully_paid(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        $paid = $this->orderWithItem($staff, ['grand_total' => 50000, 'subtotal' => 50000, 'total_paid' => 50000]);
        $html = $this->invoiceHtml($token, $paid);
        $this->assertStringContainsString('lunas-stamp', $html);
        $this->assertStringContainsString('LUNAS', $html);
        $this->assertStringContainsString('Dibayar', $html);

        $unpaid = $this->orderWithItem($staff);
        $html = $this->invoiceHtml($token, $unpaid);
        $this->assertStringNotContainsString('LUNAS', $html);

        $partial = $this->orderWithItem($staff, ['grand_total' => 50000, 'subtotal' => 50000, 'total_paid' => 20000]);
        $html = $this->invoiceHtml($token, $partial);
        $this->assertStringNotContainsString('LUNAS', $html);

        $overpaid = $this->orderWithItem($staff, ['grand_total' => 50000, 'subtotal' => 50000, 'total_paid' => 60000]);
        $html = $this->invoiceHtml($token, $overpaid);
        $this->assertStringContainsString('lunas-stamp', $html);
        $this->assertStringContainsString('LUNAS', $html);
    }

    public function test_invoice_summary_shows_ongkir_row_only_when_delivery_fee_is_positive(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        $withFee = $this->orderWithItem($staff, ['delivery_fee' => 5000]);
        $html = $this->invoiceHtml($token, $withFee);
        $this->assertStringContainsString('Ongkir', $html);
        $this->assertStringContainsString('IDR 5.000', $html);

        $withoutFee = $this->orderWithItem($staff, ['delivery_fee' => 0]);
        $html = $this->invoiceHtml($token, $withoutFee);
        $this->assertStringNotContainsString('Ongkir', $html);
    }

    public function test_invoice_pdf_with_bank_and_static_qris_fits_on_one_page(): void
    {
        Storage::fake('public');
        $tenant = Tenant::factory()->create();
        TenantSettings::for($tenant->id)->updatePayment([
            'bank_name' => 'Bank BCA',
            'bank_account_name' => 'Toko Kustom',
            'bank_account_number' => '1234567890',
        ]);
        $path = "tenants/{$tenant->id}/qris.png";
        Storage::disk('public')->put($path, $this->qrPng());
        TenantSettings::for($tenant->id)->setQrisImage($path);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;
        $order = $this->orderWithItem($staff);

        $data = $this->invoiceData($token, $order);

        $pdf = Pdf::loadView('pdf.invoice', $data);
        $pdf->output();

        $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }

    public function test_invoice_embeds_cached_mayar_dynamic_qris_as_data_uri_when_amount_matches(): void
    {
        $tenant = Tenant::factory()->create();
        TenantSettings::for($tenant->id)->setMayarApiKey('secret-key');
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;
        $order = $this->orderWithItem($staff, [
            'status' => 'pending',
            'grand_total' => 50000,
            'subtotal' => 50000,
            'total_paid' => 0,
            'mayar_qr_url' => 'https://api.mayar.id/qr/abc.png',
            'mayar_amount' => 50000,
        ]);

        Http::fake([
            'api.mayar.id/qr/abc.png' => Http::response($this->qrPng(), 200, ['Content-Type' => 'image/png']),
        ]);

        $html = $this->invoiceHtml($token, $order);

        $this->assertStringContainsString('Pembayaran', $html);
        $this->assertStringContainsString('QRIS', $html);
        $this->assertStringNotContainsString('Pembayaran QRIS', $html);
        $this->assertStringContainsString('data:image/png;base64,'.base64_encode($this->qrPng()), $html);
        $this->assertStringContainsString('Scan untuk membayar', $html);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.mayar.id/qr/abc.png');

        $order->refresh();
        $this->assertSame('https://api.mayar.id/qr/abc.png', $order->mayar_qr_url);
        $this->assertSame('50000.00', (string) $order->mayar_amount);
        $this->assertSame(0, $order->payments()->count());
    }

    public function test_invoice_shows_qris_pending_note_when_mayar_cached_amount_mismatches(): void
    {
        $tenant = Tenant::factory()->create();
        TenantSettings::for($tenant->id)->setMayarApiKey('secret-key');
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;
        $order = $this->orderWithItem($staff, [
            'status' => 'pending',
            'grand_total' => 50000,
            'subtotal' => 50000,
            'total_paid' => 0,
            'mayar_qr_url' => 'https://api.mayar.id/qr/stale.png',
            'mayar_amount' => 12345,
        ]);

        Http::fake();

        $html = $this->invoiceHtml($token, $order);

        $this->assertStringContainsString('Pembayaran', $html);
        $this->assertStringContainsString('QRIS', $html);
        $this->assertStringContainsString('Scan QRIS lewat aplikasi kasir atau hubungi toko', $html);
        $this->assertStringNotContainsString('data:image/', $html);

        Http::assertNothingSent();

        $order->refresh();
        $this->assertSame('https://api.mayar.id/qr/stale.png', $order->mayar_qr_url);
        $this->assertSame('12345.00', (string) $order->mayar_amount);
        $this->assertSame(0, $order->payments()->count());
    }

    public function test_invoice_shows_qris_pending_note_when_mayar_qr_fetch_fails(): void
    {
        $tenant = Tenant::factory()->create();
        TenantSettings::for($tenant->id)->setMayarApiKey('secret-key');
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;
        $order = $this->orderWithItem($staff, [
            'status' => 'pending',
            'grand_total' => 50000,
            'subtotal' => 50000,
            'total_paid' => 0,
            'mayar_qr_url' => 'https://api.mayar.id/qr/down.png',
            'mayar_amount' => 50000,
        ]);

        Http::fake([
            'api.mayar.id/qr/down.png' => Http::response('server error', 500),
        ]);

        $html = $this->invoiceHtml($token, $order);

        $this->assertStringContainsString('Pembayaran', $html);
        $this->assertStringContainsString('QRIS', $html);
        $this->assertStringContainsString('Scan QRIS lewat aplikasi kasir atau hubungi toko', $html);
        $this->assertStringNotContainsString('data:image/', $html);

        $order->refresh();
        $this->assertSame('https://api.mayar.id/qr/down.png', $order->mayar_qr_url);
        $this->assertSame('50000.00', (string) $order->mayar_amount);
        $this->assertSame(0, $order->payments()->count());
    }

    private function orderWithItem(User $staff, array $attributes = []): Order
    {
        $product = Product::factory()->create(['tenant_id' => $staff->tenant_id]);
        $unit = $product->units()->first();

        $order = Order::factory()->create(array_merge([
            'user_id' => $staff->id,
            'tenant_id' => $staff->tenant_id,
        ], $attributes));

        $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 50000,
            'quantity' => 1,
            'subtotal' => 50000,
        ]);

        return $order;
    }

    private function invoiceHtml(string $token, Order $order): string
    {
        return view('pdf.invoice', $this->invoiceData($token, $order))->render();
    }

    private function invoiceData(string $token, Order $order): array
    {
        $captured = [];
        View::composer('pdf.invoice', function ($view) use (&$captured) {
            $captured = $view->getData();
        });

        $this->withToken($token)
            ->get("/api/orders/{$order->id}/invoice")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        return $captured;
    }

    private function qrPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        );
    }
}
