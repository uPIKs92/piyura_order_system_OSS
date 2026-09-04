<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\SheetsSyncOutbox;
use App\Models\Tenant;
use App\Models\User;
use App\Services\GoogleSheetsService;
use App\Services\SheetsSyncService;
use App\Support\TenantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class SheetsSyncDispatchTest extends TestCase
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
        $this->product = Product::factory()->create();
        $this->product->units()->first()->update(['stok' => 50, 'harga_jual' => 10000]);

        config([
            'google.retry_attempts' => 3,
            'google.telegram_bot_token' => 'test-token',
            'google.telegram_chat_id' => '12345',
        ]);

        $settings = TenantSettings::for($this->owner->tenant_id);
        $settings->set('sheets.sync_enabled', true);
        $settings->set('sheets.spreadsheet_id', 'spreadsheet-123');
        $settings->set('google.connected', true);
        $settings->set('google.refresh_token', Crypt::encryptString('refresh-token'));
        $settings->set('google.connected_email', 'owner@example.com');
    }

    public function test_owner_dispatch_syncs_only_current_tenant(): void
    {
        $mock = Mockery::mock(GoogleSheetsService::class);
        $mock->shouldReceive('isReadyForSync')->andReturn(true);
        $mock->shouldReceive('ensureHeaderRow')->once();
        $mock->shouldReceive('appendRow')->once();
        $this->app->instance(GoogleSheetsService::class, $mock);

        $order = Order::factory()->create(['user_id' => $this->staff->id]);
        app(SheetsSyncService::class)->pushToOutbox($order);

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherOrder = Order::factory()->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
        ]);
        $otherRecord = SheetsSyncOutbox::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'order_id' => $otherOrder->id,
            'idempotency_key' => (string) Str::uuid(),
            'payload' => ['invoice_no' => $otherOrder->invoice_no],
            'status' => 'pending',
            'attempts' => 0,
        ]);

        $this->withToken($this->ownerToken)
            ->postJson('/api/sheets-sync/dispatch')
            ->assertOk()
            ->assertJsonPath('sent', 1)
            ->assertJsonPath('failed', 0)
            ->assertJsonPath('pending', 0)
            ->assertJsonPath('failed_total', 0);

        $this->assertSame('pending', $otherRecord->fresh()->status);
    }

    public function test_dispatch_returns_422_when_sync_disabled(): void
    {
        TenantSettings::for($this->owner->tenant_id)->set('sheets.sync_enabled', false);

        $this->withToken($this->ownerToken)
            ->postJson('/api/sheets-sync/dispatch')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Sync order aktif masih mati. Aktifkan "Sync order aktif" lalu simpan integrasi.');
    }

    public function test_dispatch_returns_422_when_not_connected(): void
    {
        TenantSettings::for($this->owner->tenant_id)->set('google.connected', false);

        $this->withToken($this->ownerToken)
            ->postJson('/api/sheets-sync/dispatch')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Google belum terhubung. Hubungkan Google terlebih dahulu.');
    }

    public function test_staff_forbidden(): void
    {
        $this->withToken($this->staffToken)
            ->postJson('/api/sheets-sync/dispatch')
            ->assertForbidden();
    }
}
