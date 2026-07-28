<?php

namespace Tests\Feature;

use App\Jobs\ProcessOrdersImportJob;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
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
}
