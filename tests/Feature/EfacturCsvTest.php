<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EfacturCsvTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithToken(): array
    {
        $owner = User::factory()->owner()->create();

        return [$owner, $owner->createToken('test')->plainTextToken];
    }

    private function orderWithItem(array $orderAttributes, array $itemAttributes = []): Order
    {
        $product = Product::factory()->create(['nama' => 'Kopi Tubruk', 'sku' => fake()->unique()->bothify('KP-####')]);
        $unit = $product->units()->first();

        $order = Order::factory()->create(array_merge([
            'status' => OrderStatus::Selesai,
            'order_date' => '2026-03-07',
            'customer_name' => 'Budi Santoso',
            'customer_address' => 'Jl. Merdeka No. 10',
            'ppn_amount' => 2750,
            'grand_total' => 27500,
        ], $orderAttributes));

        $order->items()->create(array_merge([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 12500,
            'cost_snapshot' => 5000,
            'quantity' => 2,
            'subtotal' => 25000,
        ], $itemAttributes));

        return $order;
    }

    private function csvLines(TestResponse $response): array
    {
        $csv = $response->streamedContent();

        return explode("\n", rtrim($csv, "\n"));
    }

    public function test_fk_and_of_records_follow_djp_csv_field_order(): void
    {
        [$owner, $token] = $this->ownerWithToken();

        $product = Product::factory()->create(['nama' => 'Kopi Tubruk', 'sku' => fake()->unique()->bothify('KP-####')]);
        $unit = $product->units()->first();
        $productNoSku = Product::factory()->create(['nama' => 'Teh Melati', 'sku' => null]);
        $unitNoSku = $productNoSku->units()->first();

        $order = Order::factory()->create([
            'status' => OrderStatus::Selesai,
            'order_date' => '2026-03-07',
            'customer_name' => 'Budi Santoso',
            'customer_address' => "Jl. Merdeka No. 10; RT 02\nJakarta",
            'ppn_amount' => 1782.53,
            'grand_total' => 17825.33,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name' => 'Kopi Tubruk',
            'satuan' => $unit->satuan,
            'price_snapshot' => 12345.67,
            'cost_snapshot' => 5000,
            'quantity' => 2,
            'subtotal' => 23456.78,
        ]);
        $order->items()->create([
            'product_id' => $productNoSku->id,
            'product_unit_id' => $unitNoSku->id,
            'product_name' => 'Teh Melati',
            'satuan' => $unitNoSku->satuan,
            'price_snapshot' => 5000,
            'cost_snapshot' => 2000,
            'quantity' => 3,
            'subtotal' => 13500,
        ]);

        $response = $this->withToken($token)->get('/api/reports/efaktur?year=2026&month=3');

        $response->assertOk();
        $this->assertSame('text/csv; charset=UTF-8', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));

        $lines = $this->csvLines($response);
        $this->assertCount(3, $lines);

        $fk = explode(';', $lines[0]);
        $this->assertCount(24, $fk);
        $this->assertSame('FK', $fk[0]);
        $this->assertSame('01', $fk[1]);
        $this->assertSame('0', $fk[2]);
        $this->assertSame(sprintf('010.000-26.%08d', $order->id), $fk[3]);
        $this->assertSame('03', $fk[4]);
        $this->assertSame('2026', $fk[5]);
        $this->assertSame('07/03/2026', $fk[6]);
        $this->assertSame('0000000000000000', $fk[7]);
        $this->assertSame('Budi Santoso', $fk[8]);
        $this->assertSame('Jl. Merdeka No. 10, RT 02 Jakarta', $fk[9]);
        for ($i = 10; $i <= 23; $i++) {
            $this->assertSame('', $fk[$i], "FK field {$i} must be blank");
        }

        $of = explode(';', $lines[1]);
        $this->assertCount(8, $of);
        $this->assertSame('OF', $of[0]);
        $this->assertSame($product->sku, $of[1]);
        $this->assertSame('Kopi Tubruk', $of[2]);
        $this->assertSame('12345.67', $of[3]);
        $this->assertSame('2', $of[4]);
        $this->assertSame('24691.34', $of[5]);
        $this->assertSame('1234.56', $of[6]);
        $this->assertSame('23456.78', $of[7]);

        $ofNoSku = explode(';', $lines[2]);
        $this->assertCount(8, $ofNoSku);
        $this->assertSame('OF', $ofNoSku[0]);
        $this->assertSame(sprintf('P%05d', $productNoSku->id), $ofNoSku[1]);
        $this->assertSame('Teh Melati', $ofNoSku[2]);
        $this->assertSame('5000.00', $ofNoSku[3]);
        $this->assertSame('3', $ofNoSku[4]);
        $this->assertSame('15000.00', $ofNoSku[5]);
        $this->assertSame('1500.00', $ofNoSku[6]);
        $this->assertSame('13500.00', $ofNoSku[7]);
    }

    public function test_month_filter_exports_only_that_month_and_default_exports_whole_year(): void
    {
        [$owner, $token] = $this->ownerWithToken();
        $slug = $owner->tenant->slug;

        $march = $this->orderWithItem(['order_date' => '2026-03-07']);
        $april = $this->orderWithItem(['order_date' => '2026-04-12']);

        $monthResponse = $this->withToken($token)->get('/api/reports/efaktur?year=2026&month=4');
        $monthResponse->assertOk();
        $this->assertStringContainsString(
            "efaktur-{$slug}-2026-04.csv",
            (string) $monthResponse->headers->get('Content-Disposition')
        );
        $monthCsv = $monthResponse->streamedContent();
        $this->assertStringContainsString(sprintf('010.000-26.%08d', $april->id), $monthCsv);
        $this->assertStringNotContainsString(sprintf('010.000-26.%08d', $march->id), $monthCsv);
        $this->assertCount(2, $this->csvLines($monthResponse));

        $yearResponse = $this->withToken($token)->get('/api/reports/efaktur?year=2026');
        $yearResponse->assertOk();
        $this->assertStringContainsString(
            "efaktur-{$slug}-2026.csv",
            (string) $yearResponse->headers->get('Content-Disposition')
        );
        $yearCsv = $yearResponse->streamedContent();
        $this->assertStringContainsString(sprintf('010.000-26.%08d', $march->id), $yearCsv);
        $this->assertStringContainsString(sprintf('010.000-26.%08d', $april->id), $yearCsv);
        $this->assertSame(2, substr_count($yearCsv, "\nFK;") + (str_starts_with($yearCsv, 'FK;') ? 1 : 0));
    }

    public function test_export_never_includes_other_tenant_orders(): void
    {
        [$owner, $token] = $this->ownerWithToken();

        $own = $this->orderWithItem(['order_date' => '2026-05-05']);

        $response = $this->withToken($token)->get('/api/reports/efaktur?year=2026');

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString(sprintf('010.000-26.%08d', $own->id), $csv);
    }

    public function test_export_excludes_draft_cancelled_and_zero_ppn_orders(): void
    {
        [$owner, $token] = $this->ownerWithToken();

        $this->orderWithItem(['status' => OrderStatus::Draft, 'order_date' => '2026-06-01']);
        $this->orderWithItem(['status' => OrderStatus::Cancelled, 'order_date' => '2026-06-02']);
        $this->orderWithItem(['status' => OrderStatus::Pending, 'order_date' => '2026-06-03', 'ppn_amount' => 0]);
        $included = $this->orderWithItem(['status' => OrderStatus::Diproses, 'order_date' => '2026-06-04']);

        $response = $this->withToken($token)->get('/api/reports/efaktur?year=2026');

        $response->assertOk();
        $lines = $this->csvLines($response);
        $this->assertCount(2, $lines);
        $this->assertStringContainsString(sprintf('010.000-26.%08d', $included->id), $lines[0]);
    }

    public function test_staff_cannot_export_efaktur_csv(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->get('/api/reports/efaktur?year=2026')
            ->assertForbidden();
    }

    public function test_year_and_month_validation(): void
    {
        [$owner, $token] = $this->ownerWithToken();

        $this->withToken($token)->getJson('/api/reports/efaktur')->assertUnprocessable();
        $this->withToken($token)->getJson('/api/reports/efaktur?year=abc')->assertUnprocessable();
        $this->withToken($token)->getJson('/api/reports/efaktur?year=2026&month=13')->assertUnprocessable();
        $this->withToken($token)->getJson('/api/reports/efaktur?year=2026&month=0')->assertUnprocessable();
        $this->withToken($token)->getJson('/api/reports/efaktur?year=2026&month=x')->assertUnprocessable();
    }
}
