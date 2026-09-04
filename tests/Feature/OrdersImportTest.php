<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Jobs\ProcessOrdersImportJob;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrdersImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class OrdersImportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
        $this->product = Product::factory()->create(['nama' => 'Kopi Arabica']);
        $this->product->units()->first()->update(['stok' => 100]);
        File::ensureDirectoryExists(config('import.path'));
    }

    public function test_imports_valid_csv_rows(): void
    {
        $path = config('import.path').'/valid.csv';
        file_put_contents($path, implode("\n", [
            'customer_name,customer_phone,order_date,product_name,quantity,status',
            'Budi,08123,'.now()->toDateString().',Kopi Arabica,2,draft',
        ]));

        $this->artisan('orders:import', ['filename' => 'valid.csv', '--mapping' => 'config'])
            ->assertSuccessful();

        $this->assertDatabaseHas('orders', ['customer_name' => 'Budi']);
        $this->assertEquals(1, Order::count());
        $this->assertDatabaseHas('customers', [
            'tenant_id' => $this->owner->tenant_id,
            'name' => 'Budi',
            'phone' => '08123',
        ]);
    }

    public function test_import_reports_invalid_rows(): void
    {
        $path = config('import.path').'/invalid.csv';
        file_put_contents($path, implode("\n", [
            'customer_name,customer_phone,order_date,product_name,quantity,status',
            'Budi,08123,'.now()->toDateString().',Missing Product,1,draft',
        ]));

        $this->artisan('orders:import', ['filename' => 'invalid.csv', '--mapping' => 'config'])
            ->assertSuccessful();

        $this->assertEquals(0, Order::count());
        $this->assertFileExists(config('import.path').'/reports');
    }

    public function test_large_import_is_queued(): void
    {
        Bus::fake();

        $lines = ['customer_name,customer_phone,order_date,product_name,quantity,status'];
        for ($i = 0; $i < 101; $i++) {
            $lines[] = "User{$i},08123,".now()->toDateString().',Kopi Arabica,1,draft';
        }
        file_put_contents(config('import.path').'/large.csv', implode("\n", $lines));

        $this->artisan('orders:import', ['filename' => 'large.csv', '--mapping' => 'config'])
            ->assertSuccessful();

        Bus::assertDispatched(ProcessOrdersImportJob::class);
    }

    public function test_imports_valid_xlsx_rows(): void
    {
        $path = config('import.path').'/valid.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['customer_name', 'customer_phone', 'order_date', 'product_name', 'quantity', 'status'],
            ['Budi', '08123', now()->toDateString(), 'Kopi Arabica', 2, 'draft'],
        ]);
        (new Xlsx($spreadsheet))->save($path);

        $this->artisan('orders:import', ['filename' => 'valid.xlsx', '--mapping' => 'config'])
            ->assertSuccessful();

        $this->assertDatabaseHas('orders', ['customer_name' => 'Budi']);
        $this->assertEquals(1, Order::count());
    }

    public function test_reimport_existing_unit_updates_prices_without_resetting_stock(): void
    {
        $unit = $this->product->units()->first();
        $unit->update(['stok' => 100, 'is_default' => true, 'harga_jual' => 1000, 'harga_beli' => 500]);

        $result = app(OrdersImportService::class)->importProductsRows([
            [
                'Product Name' => 'Kopi Arabica',
                'Unit' => 'pcs',
                'Selling Price' => 2500,
                'COGS' => 1500,
            ],
            [
                'Product Name' => 'Kopi Arabica',
                'Unit' => 'box',
                'Selling Price' => 50000,
                'COGS' => 40000,
            ],
        ], $this->owner->tenant_id);

        $this->assertSame(2, $result['success']);

        $unit = $unit->fresh();
        $this->assertEquals(100, $unit->stok);
        $this->assertEquals(2500, (float) $unit->harga_jual);
        $this->assertEquals(1500, (float) $unit->harga_beli);
        $this->assertTrue((bool) $unit->is_default);

        $newUnit = $this->product->units()->where('satuan', 'box')->first();
        $this->assertNotNull($newUnit);
        $this->assertEquals(0, $newUnit->stok);
        $this->assertEquals(50000, (float) $newUnit->harga_jual);
        $this->assertFalse((bool) $newUnit->is_default);
    }

    public function test_repair_import_status_creates_status_log_and_bumps_version(): void
    {
        $unit = $this->product->units()->first();
        $order = Order::factory()->create([
            'user_id' => $this->owner->id,
            'customer_name' => 'Budi',
            'status' => OrderStatus::Diproses,
            'order_date' => now()->toDateString(),
        ]);
        $order->items()->create([
            'product_id' => $this->product->id,
            'product_unit_id' => $unit->id,
            'product_name' => 'Kopi Arabica',
            'satuan' => $unit->satuan,
            'price_snapshot' => 1000,
            'quantity' => 2,
            'subtotal' => 2000,
        ]);

        $result = app(OrdersImportService::class)->repairOrderStatusesFromRows([
            [
                'Date' => now()->toDateString(),
                'Customer Name' => 'Budi',
                'Product Name' => 'Kopi Arabica',
                'Qty' => 2,
                'Unit' => $unit->satuan,
                'Status' => 'belum lunas',
                'Delivery' => 'Dikirim',
            ],
        ], $this->owner);

        $this->assertSame(1, $result['updated']);

        $order = $order->fresh();
        $this->assertSame(OrderStatus::Dikirim->value, $order->status->value);
        $this->assertSame(2, $order->version);
        $this->assertDatabaseHas('order_status_logs', [
            'order_id' => $order->id,
            'from_status' => 'diproses',
            'to_status' => 'dikirim',
            'changed_by' => $this->owner->id,
        ]);
    }

    public function test_imported_order_rows_without_phone_do_not_sync_customer_directory(): void
    {
        $unit = $this->product->units()->first();

        $result = app(OrdersImportService::class)->importOrdersRows([
            [
                'Date' => now()->toDateString(),
                'Customer Name' => 'Budi',
                'Product Name' => 'Kopi Arabica',
                'Qty' => 2,
                'Unit' => $unit->satuan,
                'Status' => 'belum lunas',
                'Delivery' => 'Dikirim',
            ],
        ], $this->owner);

        $this->assertSame(1, $result['success']);
        $this->assertDatabaseHas('orders', ['customer_name' => 'Budi']);
        $this->assertSame(0, Customer::count());
    }
}
