<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\GoogleOAuthService;
use App\Support\TenantSettings;
use Google\Client as GoogleClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Mockery;
use Tests\TestCase;

class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private string $ownerToken;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
        $this->ownerToken = $this->owner->createToken('test')->plainTextToken;
        $this->tenant = Tenant::query()->findOrFail($this->owner->tenant_id);

        config([
            'google.client_id' => 'test-client-id',
            'google.client_secret' => 'test-client-secret',
            'google.redirect_uri' => 'http://localhost/api/integrations/google/callback',
            'tenant.base_domain' => 'localhost',
            'app.url' => 'http://localhost:8084',
        ]);
    }

    public function test_owner_can_get_connect_url(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/integrations/google/connect')
            ->assertOk();

        $url = $response->json('url');
        $this->assertIsString($url);
        $this->assertStringContainsString('accounts.google.com', $url);
        $this->assertStringContainsString('client_id=test-client-id', $url);
    }

    public function test_staff_cannot_connect_google(): void
    {
        $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/integrations/google/connect')
            ->assertForbidden();
    }

    public function test_callback_stores_encrypted_refresh_token(): void
    {
        $client = Mockery::mock(GoogleClient::class);
        $client->shouldReceive('fetchAccessTokenWithAuthCode')
            ->once()
            ->with('auth-code')
            ->andReturn([
                'access_token' => 'access',
                'refresh_token' => 'refresh-abc',
            ]);
        $client->shouldReceive('setAccessToken')->once();

        $service = new class($client) extends GoogleOAuthService
        {
            public function __construct(private GoogleClient $stubClient) {}

            public function makeClient(): GoogleClient
            {
                return $this->stubClient;
            }
        };

        $nonce = 'nonce-test-123';
        $payload = [
            'tenant_id' => $this->tenant->id,
            'tenant_slug' => $this->tenant->slug,
            'nonce' => $nonce,
        ];
        Cache::put('google_oauth_state.'.$nonce, $payload, now()->addMinutes(15));
        $state = Crypt::encryptString(json_encode($payload));

        $result = $service->handleCallback('auth-code', $state);

        $this->assertSame($this->tenant->id, $result['tenant']->id);

        $settings = TenantSettings::for($this->tenant->id);
        $this->assertTrue($settings->googleConnected());
        $this->assertSame('refresh-abc', $settings->googleRefreshToken());
    }

    public function test_disconnect_clears_connection(): void
    {
        TenantSettings::for($this->tenant->id)->setGoogleConnection('refresh-token', 'user@example.com');

        $client = Mockery::mock(GoogleClient::class);
        $client->shouldReceive('revokeToken')->once()->with('refresh-token');

        $service = new class($client) extends GoogleOAuthService
        {
            public function __construct(private GoogleClient $stubClient) {}

            public function makeClient(): GoogleClient
            {
                return $this->stubClient;
            }
        };
        $this->app->instance(GoogleOAuthService::class, $service);

        $this->withToken($this->ownerToken)
            ->deleteJson('/api/integrations/google')
            ->assertOk()
            ->assertJsonPath('google_connected', false);

        $this->assertFalse(TenantSettings::for($this->tenant->id)->googleConnected());
        $this->assertNull(TenantSettings::for($this->tenant->id)->googleRefreshToken());
    }

    public function test_integrations_settings_shape(): void
    {
        $this->withToken($this->ownerToken)
            ->getJson('/api/settings/integrations')
            ->assertOk()
            ->assertJsonStructure([
                'sheets_sync_enabled',
                'sheets_spreadsheet_id',
                'sheets_products_tab',
                'sheets_orders_tab',
                'sheets_reporting_tab',
                'google_connected',
                'google_connected_email',
            ]);
    }

    public function test_invalid_oauth_state_is_rejected(): void
    {
        $service = app(GoogleOAuthService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->handleCallback('code', 'not-a-valid-state');
    }
}
