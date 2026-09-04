<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\SheetsSyncOutbox;
use App\Models\User;
use App\Services\GoogleSheetsService;
use App\Services\SheetsSyncService;
use App\Support\TenantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class SheetsSyncTest extends TestCase
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

    public function test_status_change_creates_outbox_record_without_pii(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->staff->id,
            'status' => OrderStatus::Draft,
            'customer_name' => 'Secret Customer',
            'customer_phone' => '08111111111',
        ]);
        $unit = $this->product->units()->first();
        $order->items()->create([
            'product_id' => $this->product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $this->product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 10000,
            'quantity' => 1,
            'subtotal' => 10000,
        ]);

        $this->withToken($this->staffToken)->putJson("/api/orders/{$order->id}", [
            'version' => $order->version,
            'status' => 'pending',
        ])->assertOk();

        $outbox = SheetsSyncOutbox::first();
        $this->assertNotNull($outbox);
        $this->assertSame('pending', $outbox->status);
        $this->assertNotEmpty($outbox->idempotency_key);
        $this->assertArrayNotHasKey('customer_name', $outbox->payload);
        $this->assertArrayNotHasKey('customer_phone', $outbox->payload);
        $this->assertArrayHasKey('invoice_no', $outbox->payload);
    }

    public function test_dispatch_marks_sent_on_success(): void
    {
        $mock = Mockery::mock(GoogleSheetsService::class);
        $mock->shouldReceive('isReadyForSync')->andReturn(true);
        $mock->shouldReceive('ensureHeaderRow')->once();
        $mock->shouldReceive('appendRow')->once();
        $this->app->instance(GoogleSheetsService::class, $mock);

        $order = Order::factory()->create(['user_id' => $this->staff->id]);
        $record = app(SheetsSyncService::class)->pushToOutbox($order);

        $sent = app(SheetsSyncService::class)->dispatchPending();

        $this->assertSame(1, $sent);
        $this->assertSame('sent', $record->fresh()->status);
    }

    public function test_dispatch_marks_failure_on_api_error(): void
    {
        $mock = Mockery::mock(GoogleSheetsService::class);
        $mock->shouldReceive('isReadyForSync')->andReturn(true);
        $mock->shouldReceive('ensureHeaderRow')->once();
        $mock->shouldReceive('appendRow')->once()->andThrow(new \RuntimeException('Sheets write failed'));
        $this->app->instance(GoogleSheetsService::class, $mock);

        $order = Order::factory()->create(['user_id' => $this->staff->id]);
        $record = app(SheetsSyncService::class)->pushToOutbox($order);

        app(SheetsSyncService::class)->dispatchPending();

        $this->assertSame(1, $record->fresh()->attempts);
        $this->assertSame('pending', $record->fresh()->status);
        $this->assertSame('Sheets write failed', $record->fresh()->last_error);
    }

    public function test_permanent_failure_sends_telegram_notification(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $mock = Mockery::mock(GoogleSheetsService::class);
        $mock->shouldReceive('isReadyForSync')->andReturn(true);
        $mock->shouldReceive('ensureHeaderRow')->once();
        $mock->shouldReceive('appendRow')->once()->andThrow(new \RuntimeException('Server error'));
        $this->app->instance(GoogleSheetsService::class, $mock);

        $order = Order::factory()->create([
            'user_id' => $this->staff->id,
            'invoice_no' => 'INV-TEST-001',
        ]);
        $record = SheetsSyncOutbox::withoutGlobalScopes()->create([
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'idempotency_key' => 'test-key',
            'payload' => ['invoice_no' => 'INV-TEST-001'],
            'status' => 'pending',
            'attempts' => 2,
        ]);

        app(SheetsSyncService::class)->dispatchPending();

        $record->refresh();
        $this->assertSame('failed', $record->status);
        $this->assertSame(3, $record->attempts);

        Http::assertSent(function ($request) use ($order) {
            return str_contains($request->url(), 'api.telegram.org')
                && str_contains($request['text'], "Order #{$order->id}")
                && str_contains($request['text'], 'INV-TEST-001');
        });
    }

    public function test_status_transition_creates_outbox_record(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->staff->id,
            'status' => OrderStatus::Pending,
            'version' => 1,
        ]);
        $unit = $this->product->units()->first();
        $order->items()->create([
            'product_id' => $this->product->id,
            'product_unit_id' => $unit->id,
            'product_name' => $this->product->nama,
            'satuan' => $unit->satuan,
            'price_snapshot' => 10000,
            'quantity' => 1,
            'subtotal' => 10000,
        ]);

        $this->withToken($this->staffToken)->putJson("/api/orders/{$order->id}", [
            'version' => 1,
            'status' => 'diproses',
        ])->assertOk();

        $this->assertDatabaseHas('sheets_sync_outbox', [
            'order_id' => $order->id,
            'status' => 'pending',
        ]);
    }

    public function test_owner_can_list_failed_and_retry(): void
    {
        $order = Order::factory()->create(['user_id' => $this->staff->id]);
        $record = SheetsSyncOutbox::withoutGlobalScopes()->create([
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'idempotency_key' => 'retry-key',
            'payload' => [],
            'status' => 'failed',
            'attempts' => 3,
            'last_error' => 'timeout',
        ]);

        $this->withToken($this->ownerToken)
            ->getJson('/api/sheets-sync?status=failed')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $record->id);

        $this->withToken($this->ownerToken)
            ->postJson("/api/sheets-sync/{$record->id}/retry")
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('attempts', 0);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->staffToken)
            ->getJson('/api/sheets-sync')
            ->assertForbidden();
    }

    public function test_second_dispatch_claims_nothing_after_first_sends(): void
    {
        // Simulates the scheduled runner and a manual owner dispatch racing:
        // the second run must find nothing pending and append nothing.
        $mock = Mockery::mock(GoogleSheetsService::class);
        $mock->shouldReceive('isReadyForSync')->andReturn(true);
        $mock->shouldReceive('ensureHeaderRow')->once();
        $mock->shouldReceive('appendRow')->once();
        $this->app->instance(GoogleSheetsService::class, $mock);

        $order = Order::factory()->create(['user_id' => $this->staff->id]);
        $service = app(SheetsSyncService::class);
        $service->pushToOutbox($order);

        $this->assertSame(1, $service->dispatchPending());
        $this->assertSame(0, $service->dispatchPending());

        $record = SheetsSyncOutbox::withoutGlobalScopes()->first();
        $this->assertSame('sent', $record->status);
    }

    public function test_dispatch_does_not_reappend_when_identical_payload_already_sent(): void
    {
        // Ambiguous failure: the first attempt reached the sheet but its
        // status update was lost, so the record was retried. The retried row
        // carries an identical payload to the already-sent row and must be
        // resolved as delivered without a second append.
        $mock = Mockery::mock(GoogleSheetsService::class);
        $mock->shouldReceive('isReadyForSync')->andReturn(true);
        $mock->shouldReceive('ensureHeaderRow')->never();
        $mock->shouldReceive('appendRow')->never();
        $this->app->instance(GoogleSheetsService::class, $mock);

        $order = Order::factory()->create(['user_id' => $this->staff->id]);
        $payload = app(SheetsSyncService::class)->buildPayload($order);

        SheetsSyncOutbox::withoutGlobalScopes()->create([
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'idempotency_key' => 'delivered-key',
            'payload' => $payload,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        $retried = SheetsSyncOutbox::withoutGlobalScopes()->create([
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'idempotency_key' => 'retried-key',
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 1,
            'last_error' => 'timeout',
        ]);

        $sent = app(SheetsSyncService::class)->dispatchPending();

        $this->assertSame(1, $sent);
        $retried->refresh();
        $this->assertSame('sent', $retried->status);
        $this->assertNotNull($retried->sent_at);
    }

    public function test_header_row_ensured_once_per_run_regardless_of_row_count(): void
    {
        $mock = Mockery::mock(GoogleSheetsService::class);
        $mock->shouldReceive('isReadyForSync')->andReturn(true);
        $mock->shouldReceive('ensureHeaderRow')->once();
        $mock->shouldReceive('appendRow')->times(3);
        $this->app->instance(GoogleSheetsService::class, $mock);

        $service = app(SheetsSyncService::class);
        for ($i = 0; $i < 3; $i++) {
            $order = Order::factory()->create(['user_id' => $this->staff->id]);
            $service->pushToOutbox($order);
        }

        $this->assertSame(3, $service->dispatchPending());
    }

    public function test_dispatch_skips_when_not_connected(): void
    {
        TenantSettings::for($this->owner->tenant_id)->set('sheets.sync_enabled', false);

        $mock = Mockery::mock(GoogleSheetsService::class);
        $mock->shouldReceive('isReadyForSync')->andReturn(false);
        $mock->shouldReceive('appendRow')->never();
        $this->app->instance(GoogleSheetsService::class, $mock);

        $order = Order::factory()->create(['user_id' => $this->staff->id]);
        app(SheetsSyncService::class)->pushToOutbox($order);

        $this->assertSame(0, app(SheetsSyncService::class)->dispatchPending());
    }
}
