<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\ImportTemplateService;
use App\Services\OrdersImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SheetsLegacyImportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
    }

    public function test_imports_products_and_orders_from_two_tab_workbook(): void
    {
        $path = $this->makeWorkbook();

        $this->artisan('orders:import', [
            'filename' => basename($path),
            '--mapping' => 'sheets_legacy',
            '--user' => $this->owner->id,
        ])->assertSuccessful();

        $this->assertDatabaseHas('products', ['nama' => 'Omega Egg Negeri']);
        $this->assertDatabaseHas('product_units', [
            'satuan' => 'krat',
            'harga_jual' => 90000,
            'harga_beli' => 80000,
        ]);

        $this->assertEquals(1, Order::count());
        $order = Order::first();
        $this->assertSame('selesai', $order->status->value);
        $this->assertSame('Dewi', $order->customer_name);
        $this->assertEquals((float) $order->grand_total, (float) $order->total_paid);
        $this->assertGreaterThan(0, (float) $order->grand_total);
    }

    public function test_product_lookup_uses_nama_and_satuan(): void
    {
        $product = Product::factory()->create(['nama' => 'Omega Egg Negeri']);
        $product->units()->first()->update([
            'satuan' => 'pack',
            'harga_jual' => 35000,
        ]);

        $path = $this->makeWorkbook();

        $this->artisan('orders:import', [
            'filename' => basename($path),
            '--mapping' => 'sheets_legacy',
            '--user' => $this->owner->id,
        ])->assertSuccessful();

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertEquals((float) $order->grand_total, (float) $order->total_paid);
        $this->assertEquals(90000, (float) $order->subtotal);
    }

    public function test_imports_indonesian_number_format_and_harga_beli_header(): void
    {
        $result = app(OrdersImportService::class)->importProductsRows([
            [
                'Product Name' => 'Dried Mango',
                'Unit' => 'ori',
                'Harga Beli' => '80.000',
                'Selling Price' => '90.000',
            ],
        ], $this->owner->tenant_id);

        $this->assertSame(1, $result['success']);
        $this->assertDatabaseHas('product_units', [
            'satuan' => 'ori',
            'harga_beli' => 80000,
            'harga_jual' => 90000,
        ]);
    }

    public function test_imports_cancelled_order_when_sheet_status_is_batal(): void
    {
        $product = Product::factory()->create(['nama' => 'Omega Egg Negeri']);
        $product->units()->first()->update(['satuan' => 'krat', 'harga_jual' => 90000]);

        $result = app(OrdersImportService::class)->importOrdersRows([
            [
                'Date' => '6/12/2026 7:00:00',
                'Customer Name' => 'Kevin',
                'Product Name' => 'Omega Egg Negeri',
                'Qty' => 1,
                'Unit' => 'krat',
                'Status' => 'Batal',
                'Delivery' => 'Batal',
            ],
        ], $this->owner);

        $this->assertSame(1, $result['success']);
        $order = Order::first();
        $this->assertSame('cancelled', $order->status->value);
        $this->assertSame(0.0, (float) $order->total_paid);
    }

    public function test_delivered_but_unpaid_order_imports_as_dikirim(): void
    {
        $product = Product::factory()->create(['nama' => 'Omega Egg Negeri']);
        $product->units()->first()->update(['satuan' => 'krat', 'harga_jual' => 90000]);

        $result = app(OrdersImportService::class)->importOrdersRows([
            [
                'Date' => '7/24/2026 7:00:00',
                'Customer Name' => 'Joel',
                'Product Name' => 'Omega Egg Negeri',
                'Qty' => 1,
                'Unit' => 'krat',
                'Status' => 'Pending',
                'Delivery' => 'Selesai',
            ],
        ], $this->owner);

        $this->assertSame(1, $result['success']);
        $order = Order::first();
        $this->assertSame('dikirim', $order->status->value);
        $this->assertSame(0.0, (float) $order->total_paid);
    }

    public function test_template_service_generates_two_tab_workbook(): void
    {
        $path = app(ImportTemplateService::class)->ensureExists();

        $this->assertFileExists($path);
        $this->assertGreaterThan(1000, filesize($path));
    }

    private function makeWorkbook(): string
    {
        $dir = config('import.path');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir.'/legacy-test.xlsx';
        $spreadsheet = new Spreadsheet;

        $products = $spreadsheet->getActiveSheet();
        $products->setTitle('Products');
        $products->fromArray([
            ['Product Name', 'Unit', 'COGS', 'Selling Price'],
            ['Omega Egg Negeri', 'krat', 80000, 90000],
        ]);

        $orders = $spreadsheet->createSheet();
        $orders->setTitle('Orders');
        $orders->fromArray([
            ['Date', 'Customer Name', 'Product Name', 'Qty', 'Unit', 'Status', 'Delivery'],
            ['6/7/2026 0:00:00', 'Dewi', 'Omega Egg Negeri', 1, 'krat', 'lunas', 'Selesai'],
        ]);

        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
