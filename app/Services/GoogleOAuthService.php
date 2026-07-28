<?php

namespace App\Services;

use App\Models\Tenant;
use App\Support\TenantSettings;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class GoogleOAuthService
{
    public function makeClient(): GoogleClient
    {
        $client = new GoogleClient;
        $client->setClientId((string) config('google.client_id'));
        $client->setClientSecret((string) config('google.client_secret'));
        $client->setRedirectUri((string) config('google.redirect_uri'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setScopes(config('google.scopes', []));

        return $client;
    }

    public function getConnectUrl(int $tenantId): string
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $nonce = Str::random(40);
        $statePayload = [
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'nonce' => $nonce,
        ];

        Cache::put($this->stateCacheKey($nonce), $statePayload, now()->addMinutes(15));

        $client = $this->makeClient();
        $client->setState(Crypt::encryptString(json_encode($statePayload)));

        return $client->createAuthUrl();
    }

    /**
     * @return array{tenant: Tenant, email: string|null}
     */
    public function handleCallback(string $code, string $state): array
    {
        $payload = $this->decodeAndValidateState($state);
        $tenant = Tenant::query()->findOrFail($payload['tenant_id']);

        $client = $this->makeClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \RuntimeException('Google OAuth failed: '.($token['error_description'] ?? $token['error']));
        }

        $refreshToken = $token['refresh_token'] ?? null;
        if (! is_string($refreshToken) || $refreshToken === '') {
            $existing = TenantSettings::for($tenant->id)->googleRefreshToken();
            if (! $existing) {
                throw new \RuntimeException('Google did not return a refresh token. Disconnect and reconnect with consent.');
            }
            $refreshToken = $existing;
        }

        $client->setAccessToken($token);
        $email = $this->fetchEmail($client);

        $settings = TenantSettings::for($tenant->id);
        $settings->setGoogleConnection($refreshToken, $email);

        Cache::forget($this->stateCacheKey($payload['nonce']));

        return ['tenant' => $tenant, 'email' => $email];
    }

    public function disconnect(int $tenantId): void
    {
        $settings = TenantSettings::for($tenantId);
        $refreshToken = $settings->googleRefreshToken();

        if ($refreshToken) {
            try {
                $client = $this->makeClient();
                $client->revokeToken($refreshToken);
            } catch (\Throwable) {
                // Best-effort revoke; always clear local credentials.
            }
        }

        $settings->clearGoogleConnection();
    }

    public function getClientForTenant(int $tenantId): GoogleClient
    {
        $settings = TenantSettings::for($tenantId);
        $refreshToken = $settings->googleRefreshToken();

        if (! $refreshToken) {
            throw new \RuntimeException('Google Sheets is not connected for this tenant.');
        }

        $client = $this->makeClient();
        $client->fetchAccessTokenWithRefreshToken($refreshToken);

        $token = $client->getAccessToken();
        if (isset($token['error'])) {
            throw new \RuntimeException('Failed to refresh Google token: '.($token['error_description'] ?? $token['error']));
        }

        if (! empty($token['refresh_token']) && is_string($token['refresh_token'])) {
            $settings->set('google.refresh_token', Crypt::encryptString($token['refresh_token']));
        }

        return $client;
    }

    /**
     * @return array{tenant_id: int, tenant_slug: string, nonce: string}
     */
    private function decodeAndValidateState(string $state): array
    {
        try {
            $decoded = json_decode(Crypt::decryptString($state), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('Invalid OAuth state.', 0, $e);
        }

        if (! is_array($decoded)
            || ! isset($decoded['tenant_id'], $decoded['tenant_slug'], $decoded['nonce'])
            || ! is_string($decoded['nonce'])
        ) {
            throw new \InvalidArgumentException('Invalid OAuth state payload.');
        }

        $cached = Cache::get($this->stateCacheKey($decoded['nonce']));
        if (! is_array($cached)
            || (int) $cached['tenant_id'] !== (int) $decoded['tenant_id']
            || ($cached['tenant_slug'] ?? null) !== $decoded['tenant_slug']
        ) {
            throw new \InvalidArgumentException('OAuth state expired or mismatched.');
        }

        return [
            'tenant_id' => (int) $decoded['tenant_id'],
            'tenant_slug' => (string) $decoded['tenant_slug'],
            'nonce' => (string) $decoded['nonce'],
        ];
    }

    private function fetchEmail(GoogleClient $client): ?string
    {
        try {
            $oauth = new \Google\Service\Oauth2($client);
            $info = $oauth->userinfo->get();

            return $info->getEmail();
        } catch (\Throwable) {
            return null;
        }
    }

    private function stateCacheKey(string $nonce): string
    {
        return 'google_oauth_state.'.$nonce;
    }
}
