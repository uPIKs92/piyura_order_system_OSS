<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_export_only_their_tenant_data(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'toko-a']);
        $tenantB = Tenant::factory()->create(['slug' => 'toko-b']);
        $ownerA = User::factory()->owner()->create(['tenant_id' => $tenantA->id]);
        User::factory()->owner()->create(['tenant_id' => $tenantB->id]);

        Order::factory()->create(['tenant_id' => $tenantA->id, 'user_id' => $ownerA->id]);
        Order::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAs($ownerA)
            ->getJson('/api/tenant/export')
            ->assertOk()
            ->assertJsonPath('tenant.slug', 'toko-a')
            ->assertJsonCount(1, 'orders');
    }

    public function test_staff_cannot_export_tenant_data(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)
            ->getJson('/api/tenant/export')
            ->assertForbidden();
    }

    public function test_global_backup_routes_are_not_available_to_tenant_owners(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->getJson('/api/backups')->assertNotFound();
        $this->actingAs($owner)->postJson('/api/backups/run')->assertNotFound();
    }

    public function test_cross_tenant_product_unit_is_rejected_on_order_create(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $staffA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $productB = Product::factory()->create(['tenant_id' => $tenantB->id]);
        $unitB = $productB->units()->first();

        $this->actingAs($staffA)
            ->postJson('/api/orders', [
                'items' => [[
                    'product_unit_id' => $unitB->id,
                    'quantity' => 1,
                ]],
            ])
            ->assertNotFound();
    }

    public function test_invoice_requires_order_authorization(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->create(['tenant_id' => $owner->tenant_id]);
        $order = Order::factory()->create(['tenant_id' => $owner->tenant_id, 'user_id' => $owner->id]);

        $this->actingAs($staff)
            ->getJson("/api/orders/{$order->id}/invoice")
            ->assertForbidden();
    }

    public function test_security_headers_are_present(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('script-src', $csp);
        $this->assertStringContainsString("'nonce-", $csp);
        $response->assertSee('nonce="', false);
        $response->assertSee('window.__TENANT_BRANDING__', false);
    }

    public function test_google_connect_requires_owner(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/integrations/google/connect')
            ->assertForbidden();
    }

    public function test_ops_backup_response_is_redacted(): void
    {
        config(['backup.n8n_secret' => 'ops-secret']);

        $response = $this->withHeader('X-N8N-Backup-Secret', 'ops-secret')
            ->postJson('/api/ops/backup');

        $response->assertOk()
            ->assertJsonMissingPath('output');

        if ($response->json('backup')) {
            $this->assertArrayNotHasKey('path', $response->json('backup'));
        }
    }

    public function test_owner_cannot_download_global_backup_records(): void
    {
        Backup::create([
            'name' => 'global.zip',
            'disk' => 'backups',
            'size' => 1024,
            'path' => 'order-tracker/global.zip',
            'checksum' => 'abc',
            'status' => 'success',
            'created_at' => now(),
        ]);

        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->get('/api/backups/1/download')
            ->assertNotFound();
    }

    public function test_invalid_logo_upload_is_rejected(): void
    {
        Storage::fake('public');
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->post('/api/settings/tenant/logo', [
                'logo' => \Illuminate\Http\UploadedFile::fake()->create('logo.txt', 10, 'text/plain'),
            ])
            ->assertStatus(422);
    }
}
