<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\TenantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReportPrintTest extends TestCase
{
    use RefreshDatabase;

    private function seedOrderWithItem(string $date = '2026-07-15'): Order
    {
        $staff = User::factory()->create(['name' => 'Budi']);
        $product = Product::factory()->create(['nama' => 'Kopi']);
        $unit = $product->units()->first();

        $order = Order::factory()->create([
            'user_id' => $staff->id,
            'order_date' => $date,
            'subtotal' => 40000,
            'grand_total' => 40000,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 20000,
            'cost_snapshot' => 8000,
            'quantity' => 2,
            'subtotal' => 40000,
        ]);

        return $order;
    }

    private function assertPdfResponse(TestResponse $response): void
    {
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_owner_can_print_daily_report_pdf(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;
        $this->seedOrderWithItem();

        $response = $this->withToken($token)
            ->get('/api/reports/print?type=daily&from=2026-07-01&to=2026-07-30');

        $this->assertPdfResponse($response);
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_owner_can_print_all_staff_report_pdf(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;
        $order = $this->seedOrderWithItem();

        $response = $this->withToken($token)
            ->get('/api/reports/print?type=staff&staff_id=all&from=2026-07-01&to=2026-07-30');

        $this->assertPdfResponse($response);
    }

    public function test_owner_can_print_all_staff_report_pdf_with_unpaid_orders(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;
        $order = $this->seedOrderWithItem();
        $order->update(['status' => OrderStatus::Pending, 'total_paid' => 25000]);

        $response = $this->withToken($token)
            ->get('/api/reports/print?type=staff&staff_id=all&from=2026-07-01&to=2026-07-30');

        $this->assertPdfResponse($response);
    }

    public function test_owner_can_print_single_staff_report_pdf(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;
        $order = $this->seedOrderWithItem();

        $response = $this->withToken($token)
            ->get("/api/reports/print?type=staff&staff_id={$order->user_id}&from=2026-07-01&to=2026-07-30");

        $this->assertPdfResponse($response);
    }

    public function test_owner_can_print_single_staff_report_pdf_with_unpaid_orders(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;
        $order = $this->seedOrderWithItem();
        $order->update(['status' => OrderStatus::Diproses, 'total_paid' => 10000]);

        $response = $this->withToken($token)
            ->get("/api/reports/print?type=staff&staff_id={$order->user_id}&from=2026-07-01&to=2026-07-30");

        $this->assertPdfResponse($response);
    }

    public function test_owner_can_print_all_product_report_pdf(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;
        $this->seedOrderWithItem();

        $response = $this->withToken($token)
            ->get('/api/reports/print?type=product&product_id=all&from=2026-07-01&to=2026-07-30');

        $this->assertPdfResponse($response);
    }

    public function test_owner_can_print_single_product_report_pdf(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;
        $order = $this->seedOrderWithItem();
        $productId = $order->items()->first()->product_id;

        $response = $this->withToken($token)
            ->get("/api/reports/print?type=product&product_id={$productId}&from=2026-07-01&to=2026-07-30");

        $this->assertPdfResponse($response);
    }

    public function test_owner_can_print_status_report_pdf(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;
        $this->seedOrderWithItem();

        $response = $this->withToken($token)
            ->get('/api/reports/print?type=status&from=2026-07-01&to=2026-07-30');

        $this->assertPdfResponse($response);
    }

    public function test_owner_can_print_status_report_pdf_without_period(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;
        $this->seedOrderWithItem();

        $response = $this->withToken($token)->get('/api/reports/print?type=status');

        $this->assertPdfResponse($response);
    }

    public function test_owner_can_print_status_report_pdf_with_unpaid_orders(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;
        $order = $this->seedOrderWithItem();
        $order->update(['status' => OrderStatus::Pending, 'total_paid' => 25000]);

        $response = $this->withToken($token)
            ->get('/api/reports/print?type=status&from=2026-07-01&to=2026-07-30');

        $this->assertPdfResponse($response);
    }

    public function test_owner_can_print_tax_report_pdf(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;
        TenantSettings::for($owner->tenant_id)
            ->updatePajak(['npwp' => '12.345.678.9-012.345', 'pph_mode' => 'umkm_non_pkp']);
        $order = $this->seedOrderWithItem('2026-07-15');
        $order->update([
            'status' => OrderStatus::Pending,
            'ppn_amount' => 4400,
            'grand_total' => 44400,
        ]);

        $response = $this->withToken($token)
            ->get('/api/reports/print?type=tax&year=2026');

        $this->assertPdfResponse($response);
        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('inline', $disposition);
        $this->assertStringContainsString('laporan-pajak-2026.pdf', $disposition);
    }

    public function test_print_tax_report_download_returns_attachment(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;
        $this->seedOrderWithItem('2026-07-15');

        $response = $this->withToken($token)
            ->get('/api/reports/print?type=tax&year=2026&download=1');

        $response->assertOk();
        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('laporan-pajak-2026.pdf', $disposition);
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_print_tax_report_validation_fails_without_year(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/reports/print?type=tax')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('year');
    }

    public function test_staff_cannot_print_tax_report(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->get('/api/reports/print?type=tax&year=2026')
            ->assertForbidden();
    }

    public function test_print_report_download_returns_attachment(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->get('/api/reports/print?type=daily&from=2026-07-01&to=2026-07-30&download=1');

        $response->assertOk();
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_staff_cannot_print_report(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->get('/api/reports/print?type=daily&from=2026-07-01&to=2026-07-30')
            ->assertForbidden();
    }

    public function test_print_report_validation_fails_when_from_after_to(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/reports/print?type=daily&from=2026-07-30&to=2026-07-01')
            ->assertUnprocessable();
    }

    public function test_print_report_validation_fails_with_invalid_type(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/reports/print?type=unknown&from=2026-07-01&to=2026-07-30')
            ->assertUnprocessable();
    }
}
