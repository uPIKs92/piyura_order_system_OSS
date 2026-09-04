<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\GoogleSheetsService;
use App\Support\TenantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Mockery;
use Tests\TestCase;

class GoogleSheetsImportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private string $ownerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
        $this->ownerToken = $this->owner->createToken('test')->plainTextToken;

        $settings = TenantSettings::for($this->owner->tenant_id);
        $settings->set('sheets.spreadsheet_id', 'sheet-id-1');
        $settings->set('google.connected', true);
        $settings->set('google.refresh_token', Crypt::encryptString('refresh-token'));
        $settings->set('sheets.products_tab', 'Products');
        $settings->set('sheets.orders_tab', 'Orders');
    }

    public function test_import_from_google_sheets(): void
    {
        $mock = Mockery::mock(GoogleSheetsService::class);
        $mock->shouldReceive('readTab')
            ->once()
            ->withArgs(fn ($tenantId, $spreadsheetId, $tab) => $tab === 'Products')
            ->andReturn([
                [
                    'Product Name' => 'Omega Egg',
                    'Unit' => 'pack',
                    'COGS' => 28000,
                    'Selling Price' => 35000,
                ],
            ]);
        $mock->shouldReceive('readTab')
            ->once()
            ->withArgs(fn ($tenantId, $spreadsheetId, $tab) => $tab === 'Orders')
            ->andReturn([
                [
                    'Date' => '2026-07-06',
                    'Customer Name' => 'Dewi',
                    'Product Name' => 'Omega Egg',
                    'Qty' => 1,
                    'Unit' => 'pack',
                    'Status' => 'lunas',
                    'Delivery' => 'Selesai',
                ],
            ]);
        $this->app->instance(GoogleSheetsService::class, $mock);

        $this->withToken($this->ownerToken)
            ->postJson('/api/integrations/google/import')
            ->assertOk()
            ->assertJsonPath('products.success', 1)
            ->assertJsonPath('orders.success', 1);

        $this->assertDatabaseHas('products', [
            'tenant_id' => $this->owner->tenant_id,
            'nama' => 'Omega Egg',
        ]);

        $this->assertDatabaseHas('product_units', [
            'satuan' => 'pack',
            'harga_beli' => 28000,
            'harga_jual' => 35000,
        ]);
    }

    public function test_import_requires_connection(): void
    {
        TenantSettings::for($this->owner->tenant_id)->clearGoogleConnection();

        $this->withToken($this->ownerToken)
            ->postJson('/api/integrations/google/import')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Google Sheets is not connected.');
    }

    public function test_import_requires_spreadsheet_id(): void
    {
        TenantSettings::for($this->owner->tenant_id)->set('sheets.spreadsheet_id', null);

        $this->withToken($this->ownerToken)
            ->postJson('/api/integrations/google/import')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Spreadsheet ID is required.');
    }
}
