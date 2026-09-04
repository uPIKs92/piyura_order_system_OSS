<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrdersImportService;
use App\Services\TenantImportResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantFreshSheetsImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_clears_tenant_business_data(): void
    {
        $owner = User::factory()->owner()->create();
        $category = Category::factory()->create(['tenant_id' => $owner->tenant_id]);
        $product = Product::factory()->create([
            'tenant_id' => $owner->tenant_id,
            'category_id' => $category->id,
        ]);
        Order::factory()->create(['user_id' => $owner->id]);
        Expense::factory()->create(['tenant_id' => $owner->tenant_id]);
        Expense::factory()->create(['tenant_id' => $owner->tenant_id]);

        $deleted = app(TenantImportResetService::class)->resetBusinessData($owner->tenant_id);

        $this->assertSame(1, $deleted['orders']);
        $this->assertSame(1, $deleted['products']);
        $this->assertSame(1, $deleted['categories']);
        $this->assertSame(2, $deleted['expenses']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }

    public function test_fresh_import_epoch_allows_reimporting_same_rows(): void
    {
        $owner = User::factory()->owner()->create();
        $product = Product::factory()->create(['nama' => 'Omega Egg Negeri']);
        $product->units()->first()->update(['satuan' => 'krat', 'harga_jual' => 90000, 'harga_beli' => 80000]);

        $rows = [[
            'Date' => '6/7/2026 0:00:00',
            'Customer Name' => 'Dewi',
            'Product Name' => 'Omega Egg Negeri',
            'Qty' => 1,
            'Unit' => 'krat',
            'Status' => 'lunas',
            'Delivery' => 'Selesai',
        ]];

        $service = app(OrdersImportService::class);
        $this->assertSame(1, $service->importOrdersRows($rows, $owner)['success']);

        app(TenantImportResetService::class)->resetBusinessData($owner->tenant_id);
        Product::factory()->create(['nama' => 'Omega Egg Negeri', 'tenant_id' => $owner->tenant_id])
            ->units()->first()->update(['satuan' => 'krat', 'harga_jual' => 90000, 'harga_beli' => 80000]);

        $this->assertSame(1, $service->importOrdersRows($rows, $owner, 2, $owner->tenant_id, true)['success']);
        $this->assertSame(1, Order::count());
    }
}
