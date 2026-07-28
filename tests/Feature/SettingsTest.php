<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PpnSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
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
                'app_name' => 'Order Tracker',
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

    public function test_owner_can_select_tweakcn_preset_palette(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/settings/tenant/appearance', [
                'theme_mode' => 'light',
                'theme_palette' => 'mx-brutalist',
            ])
            ->assertOk()
            ->assertJson([
                'theme_mode' => 'light',
                'theme_palette' => 'mx-brutalist',
            ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $owner->tenant_id,
            'theme_palette' => 'mx-brutalist',
        ]);
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
}
