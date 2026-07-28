<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private string $ownerToken;

    private User $staff;

    private string $staffToken;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
        $this->ownerToken = $this->owner->createToken('test')->plainTextToken;
        $this->staff = User::factory()->create();
        $this->staffToken = $this->staff->createToken('test')->plainTextToken;
        $this->product = Product::factory()->create(['nama' => 'Kopi Arabica']);
        $this->product->units()->first()->update(['stok' => 100]);
        File::ensureDirectoryExists(config('import.path'));
    }

    public function test_owner_can_upload_csv_import(): void
    {
        $csv = implode("\n", [
            'customer_name,customer_phone,order_date,product_name,quantity,status',
            'Budi,08123,'.now()->toDateString().',Kopi Arabica,2,draft',
        ]);

        $file = UploadedFile::fake()->createWithContent('orders.csv', $csv);

        $this->withToken($this->ownerToken)
            ->post('/api/imports', ['file' => $file])
            ->assertOk()
            ->assertJsonFragment(['success' => 1, 'total' => 1]);

        $this->assertDatabaseHas('orders', ['customer_name' => 'Budi']);
        $this->assertEquals(1, Order::count());
    }

    public function test_staff_cannot_upload_import(): void
    {
        $file = UploadedFile::fake()->create('orders.csv', 10, 'text/csv');

        $this->withToken($this->staffToken)
            ->post('/api/imports', ['file' => $file])
            ->assertForbidden();
    }

    public function test_owner_can_download_import_template(): void
    {
        $this->withToken($this->ownerToken)
            ->get('/api/imports/template')
            ->assertOk()
            ->assertHeader('content-disposition');
    }
}
