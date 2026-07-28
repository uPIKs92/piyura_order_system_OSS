<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAssetUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_host_asset_urls_omit_origin_port(): void
    {
        config([
            'tenant.base_domain' => 'piyuralabs.com',
            'tenant.marketing_subdomain' => 'sos',
            'tenant.admin_subdomain' => 'sos-admin',
            'app.url' => 'https://sos.piyuralabs.com',
            'app.env' => 'production',
        ]);

        $this->app->detectEnvironment(fn () => 'production');

        $response = $this->call(
            'GET',
            'https://sos.piyuralabs.com/',
            server: [
                'HTTP_HOST' => 'sos.piyuralabs.com',
                'SERVER_NAME' => 'sos.piyuralabs.com',
                'SERVER_PORT' => '8084',
                'HTTPS' => 'on',
                'REQUEST_SCHEME' => 'https',
            ],
        );

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringNotContainsString(':8084/build/', $html);
        $this->assertMatchesRegularExpression(
            '#https://sos\.piyuralabs\.com/build/assets/#',
            $html
        );
    }

    public function test_localhost_asset_urls_keep_dev_port(): void
    {
        config([
            'tenant.base_domain' => 'localhost',
            'app.url' => 'http://localhost:8084',
        ]);

        $response = $this->call(
            'GET',
            'http://sos.localhost:8084/',
            server: [
                'HTTP_HOST' => 'sos.localhost:8084',
                'SERVER_NAME' => 'sos.localhost',
                'SERVER_PORT' => '8084',
                'REQUEST_SCHEME' => 'http',
            ],
        );

        $response->assertOk();
        $this->assertStringContainsString(':8084/build/', $response->getContent());
    }
}
