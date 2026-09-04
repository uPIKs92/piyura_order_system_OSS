<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PpnSettings;
use App\Support\TenantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('public');
    }

    public function test_owner_can_read_and_update_ppn_settings(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/settings/ppn')
            ->assertOk()
            ->assertJsonStructure(['enabled', 'percentage']);

        $this->withToken($token)
            ->patchJson('/api/settings/ppn', ['enabled' => false, 'percentage' => 10])
            ->assertOk()
            ->assertJson(['enabled' => false, 'percentage' => 10]);

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $owner->tenant_id,
            'key' => 'ppn.enabled',
            'value' => 'false',
        ]);
    }

    public function test_staff_cannot_update_ppn_settings(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/settings/ppn', ['enabled' => false, 'percentage' => 10])
            ->assertForbidden();
    }

    public function test_owner_can_read_default_pajak_settings(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/settings/pajak')
            ->assertOk()
            ->assertJson([
                'npwp' => null,
                'pph_mode' => 'umkm_non_pkp',
            ])
            ->assertJsonStructure([
                'npwp', 'pph_mode', 'ppn' => ['enabled', 'percentage'],
            ]);
    }

    public function test_owner_can_update_pajak_settings(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/settings/pajak', [
                'npwp' => '12.345.678.9-012.345',
                'pph_mode' => 'umkm_pkp_22',
            ])
            ->assertOk()
            ->assertJson([
                'npwp' => '12.345.678.9-012.345',
                'pph_mode' => 'umkm_pkp_22',
            ]);

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $owner->tenant_id,
            'key' => 'pajak.npwp',
            'value' => '"12.345.678.9-012.345"',
        ]);

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $owner->tenant_id,
            'key' => 'pajak.pph_mode',
            'value' => '"umkm_pkp_22"',
        ]);
    }

    public function test_pajak_update_rejects_invalid_npwp_format(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/settings/pajak', ['npwp' => '12.345.678.9 abc'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('npwp');
    }

    public function test_pajak_update_rejects_invalid_pph_mode(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/settings/pajak', ['pph_mode' => 'pkp_23'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pph_mode');
    }

    public function test_staff_cannot_access_pajak_settings(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/settings/pajak')
            ->assertForbidden();

        $this->withToken($token)
            ->patchJson('/api/settings/pajak', ['npwp' => '1234567890123456'])
            ->assertForbidden();
    }

    public function test_owner_can_read_default_delivery_settings(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/settings/delivery')
            ->assertOk()
            ->assertJson([
                'fee_mode' => 'per_km',
                'fee_per_km' => 5000,
                'min_fee' => 0,
                'fixed_fee' => 10000,
                'google_maps_configured' => false,
            ])
            ->assertJsonStructure([
                'fee_mode', 'fee_per_km', 'min_fee', 'fixed_fee', 'google_maps_configured',
            ]);
    }

    public function test_owner_can_update_delivery_settings(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/settings/delivery', [
                'fee_mode' => 'per_km',
                'fee_per_km' => 6500,
                'min_fee' => 10000,
                'fixed_fee' => 15000,
            ])
            ->assertOk()
            ->assertJson([
                'fee_mode' => 'per_km',
                'fee_per_km' => 6500,
                'min_fee' => 10000,
                'fixed_fee' => 15000,
            ]);

        $this->withToken($token)
            ->patchJson('/api/settings/delivery', ['fee_mode' => 'fixed'])
            ->assertOk()
            ->assertJson([
                'fee_mode' => 'fixed',
                'fee_per_km' => 6500,
                'fixed_fee' => 15000,
            ]);

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $owner->tenant_id,
            'key' => 'delivery.fee_mode',
            'value' => '"fixed"',
        ]);

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $owner->tenant_id,
            'key' => 'delivery.fee_per_km',
            'value' => '6500',
        ]);
    }

    public function test_staff_cannot_access_delivery_settings(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/settings/delivery')
            ->assertForbidden();

        $this->withToken($token)
            ->patchJson('/api/settings/delivery', ['fee_mode' => 'fixed'])
            ->assertForbidden();
    }

    public function test_google_maps_api_key_is_stored_encrypted_and_never_echoed(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->patchJson('/api/settings/delivery', ['google_maps_api_key' => 'AIza-test-secret-key'])
            ->assertOk()
            ->assertJson(['google_maps_configured' => true]);

        $responseContent = $response->getContent();
        $this->assertStringNotContainsString('AIza-test-secret-key', $responseContent);
        $this->assertArrayNotHasKey('google_maps_api_key', $response->json());

        $stored = DB::table('tenant_settings')
            ->where('tenant_id', $owner->tenant_id)
            ->where('key', 'delivery.google_maps_api_key')
            ->value('value');
        $this->assertNotNull($stored);
        $this->assertStringNotContainsString('AIza-test-secret-key', $stored);

        $this->assertEquals('AIza-test-secret-key', TenantSettings::for($owner->tenant_id)->googleMapsApiKey());
    }

    public function test_absent_google_maps_api_key_field_keeps_stored_key(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/settings/delivery', ['google_maps_api_key' => 'AIza-test-secret-key'])
            ->assertOk()
            ->assertJson(['google_maps_configured' => true]);

        $this->withToken($token)
            ->patchJson('/api/settings/delivery', ['fee_per_km' => 7500])
            ->assertOk()
            ->assertJson([
                'fee_per_km' => 7500,
                'google_maps_configured' => true,
            ]);

        $this->assertEquals('AIza-test-secret-key', TenantSettings::for($owner->tenant_id)->googleMapsApiKey());
    }

    public function test_empty_google_maps_api_key_clears_stored_key(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/settings/delivery', ['google_maps_api_key' => 'AIza-test-secret-key'])
            ->assertOk()
            ->assertJson(['google_maps_configured' => true]);

        $this->withToken($token)
            ->patchJson('/api/settings/delivery', ['google_maps_api_key' => ''])
            ->assertOk()
            ->assertJson(['google_maps_configured' => false]);

        $this->assertDatabaseMissing('tenant_settings', [
            'tenant_id' => $owner->tenant_id,
            'key' => 'delivery.google_maps_api_key',
        ]);
    }

    public function test_delivery_update_rejects_invalid_fee_mode(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/settings/delivery', ['fee_mode' => 'per_mile'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fee_mode');
    }

    public function test_delivery_update_rejects_non_numeric_fee(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/settings/delivery', ['fee_per_km' => 'lima-ribu'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fee_per_km');
    }

    public function test_delivery_update_rejects_negative_fee(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/settings/delivery', ['min_fee' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('min_fee');
    }

    public function test_delivery_update_rejects_fee_over_max(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/settings/delivery', ['fixed_fee' => 100000001])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fixed_fee');
    }

    public function test_owner_can_view_backup_settings(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/settings/backup')
            ->assertOk()
            ->assertJsonStructure(['disk', 'driver', 'folder', 'backup_name']);
    }

    public function test_authenticated_user_can_read_tenant_settings(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/settings/tenant')
            ->assertOk()
            ->assertJsonStructure([
                'id', 'slug', 'name', 'tagline', 'address', 'phone', 'email',
                'logo_url', 'invoice_footer_text', 'theme_mode', 'theme_palette',
            ]);
    }

    public function test_owner_can_update_tenant_settings(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/settings/tenant', [
                'name' => 'Toko Baru',
                'tagline' => 'Fresh daily',
                'address' => 'Jl. Test 1',
                'phone' => '08123456789',
                'email' => 'toko@example.com',
                'invoice_footer_text' => 'Terima kasih!',
            ])
            ->assertOk()
            ->assertJson([
                'name' => 'Toko Baru',
                'tagline' => 'Fresh daily',
            ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $owner->tenant_id,
            'name' => 'Toko Baru',
        ]);
    }

    public function test_staff_cannot_update_tenant_settings(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/settings/tenant', ['name' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_owner_can_store_and_clear_delivery_origin_pin(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/settings/tenant', [
                'name' => 'Toko Contoh',
                'latitude' => -6.2,
                'longitude' => 106.8166667,
            ])
            ->assertOk()
            ->assertJsonPath('latitude', -6.2)
            ->assertJsonPath('longitude', 106.8166667);

        // The read path echoes the stored pin...
        $this->withToken($token)
            ->getJson('/api/settings/tenant')
            ->assertOk()
            ->assertJsonPath('latitude', -6.2)
            ->assertJsonPath('longitude', 106.8166667);

        // ...and absent keys keep it.
        $this->withToken($token)
            ->patchJson('/api/settings/tenant', ['name' => 'Toko Contoh'])
            ->assertOk()
            ->assertJsonPath('latitude', -6.2)
            ->assertJsonPath('longitude', 106.8166667);

        // Explicit nulls clear the pin.
        $this->withToken($token)
            ->patchJson('/api/settings/tenant', [
                'name' => 'Toko Contoh',
                'latitude' => null,
                'longitude' => null,
            ])
            ->assertOk()
            ->assertJsonPath('latitude', null)
            ->assertJsonPath('longitude', null);

        $this->assertDatabaseMissing('tenant_settings', [
            'tenant_id' => $owner->tenant_id,
            'key' => 'delivery.origin_lat',
        ]);
        $this->assertDatabaseMissing('tenant_settings', [
            'tenant_id' => $owner->tenant_id,
            'key' => 'delivery.origin_lon',
        ]);
    }

    public function test_owner_can_upload_and_delete_tenant_logo(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $file = UploadedFile::fake()->image('logo.png', 100, 100);

        $this->withToken($token)
            ->post('/api/settings/tenant/logo', ['logo' => $file])
            ->assertOk()
            ->assertJsonPath('logo_url', fn ($url) => is_string($url) && $url !== '');

        $tenant = Tenant::find($owner->tenant_id);
        $this->assertNotNull($tenant->logo_path);
        Storage::disk('public')->assertExists($tenant->logo_path);

        $this->withToken($token)
            ->deleteJson('/api/settings/tenant/logo')
            ->assertOk()
            ->assertJsonPath('logo_url', null);

        $tenant->refresh();
        $this->assertNull($tenant->logo_path);
    }

    public function test_authenticated_user_can_read_app_branding(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/settings/app')
            ->assertOk()
            ->assertJson([
                'app_name' => 'Simple Order Systems',
                'platform_name' => 'Piyuralabs',
            ]);
    }

    public function test_owner_can_update_tenant_appearance(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/settings/tenant/appearance', [
                'theme_mode' => 'dark',
                'theme_palette' => 'blue',
            ])
            ->assertOk()
            ->assertJson([
                'theme_mode' => 'dark',
                'theme_palette' => 'blue',
            ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $owner->tenant_id,
            'theme_mode' => 'dark',
            'theme_palette' => 'blue',
        ]);
    }

    public function test_owner_can_select_shadcnpreset_preset_palette(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/settings/tenant/appearance', [
                'theme_mode' => 'light',
                'theme_palette' => 'luma-lime',
            ])
            ->assertOk()
            ->assertJson([
                'theme_mode' => 'light',
                'theme_palette' => 'luma-lime',
            ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $owner->tenant_id,
            'theme_palette' => 'luma-lime',
        ]);
    }

    public function test_removed_preset_palette_fails_validation(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/settings/tenant/appearance', [
                'theme_mode' => 'light',
                'theme_palette' => 'claude-plus',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('theme_palette');
    }

    public function test_staff_cannot_update_tenant_appearance(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/settings/tenant/appearance', [
                'theme_mode' => 'dark',
                'theme_palette' => 'blue',
            ])
            ->assertForbidden();
    }

    public function test_staff_can_read_tenant_theme_in_settings(): void
    {
        $tenant = Tenant::factory()->create([
            'theme_mode' => 'light',
            'theme_palette' => 'green',
        ]);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/settings/tenant')
            ->assertOk()
            ->assertJson([
                'theme_mode' => 'light',
                'theme_palette' => 'green',
            ]);
    }

    public function test_owner_can_read_and_update_payment_settings(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/settings/payment')
            ->assertOk()
            ->assertJsonStructure(['bank_name', 'bank_account_name', 'bank_account_number', 'qris_image_url']);

        $this->withToken($token)
            ->patchJson('/api/settings/payment', [
                'bank_name' => 'BCA',
                'bank_account_name' => 'Toko Tester',
                'bank_account_number' => '1234567890',
            ])
            ->assertOk()
            ->assertJson([
                'bank_name' => 'BCA',
                'bank_account_name' => 'Toko Tester',
                'bank_account_number' => '1234567890',
            ]);
    }

    public function test_staff_cannot_update_payment_settings(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/settings/payment', ['bank_name' => 'BCA'])
            ->assertForbidden();
    }

    public function test_owner_can_upload_and_delete_qris_image(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $file = UploadedFile::fake()->image('qris.png', 100, 100);

        $this->withToken($token)
            ->post('/api/settings/payment/qris', ['qris' => $file])
            ->assertOk()
            ->assertJsonPath('qris_image_url', fn ($url) => is_string($url) && $url !== '');

        Storage::disk('public')->assertExists("tenants/{$owner->tenant_id}/qris.png");

        $this->withToken($token)
            ->deleteJson('/api/settings/payment/qris')
            ->assertOk()
            ->assertJsonPath('qris_image_url', null);

        Storage::disk('public')->assertMissing("tenants/{$owner->tenant_id}/qris.png");
    }
}

