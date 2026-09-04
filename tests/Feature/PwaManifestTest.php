<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PwaManifestTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_file_exists_and_is_valid_json(): void
    {
        $path = public_path('manifest.json');
        $this->assertTrue(File::exists($path), 'manifest.json missing from /public');

        $manifest = json_decode(File::get($path), true);
        $this->assertIsArray($manifest);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/?source=pwa', $manifest['start_url']);
        $this->assertSame('/?source=pwa', $manifest['id']);
        $this->assertSame(['window-controls-overlay', 'standalone'], $manifest['display_override']);
        $this->assertSame('navigate-existing', $manifest['launch_handler']['client_mode']);
        $this->assertFalse($manifest['prefer_related_applications']);
        $this->assertNotEmpty($manifest['icons']);
        $this->assertGreaterThanOrEqual(3, count($manifest['shortcuts']));
        $this->assertGreaterThanOrEqual(4, count($manifest['screenshots']));

        $purposes = array_column($manifest['icons'], 'purpose');
        $this->assertContains('any', $purposes);
        $this->assertContains('maskable', $purposes);
    }

    public function test_service_worker_and_offline_page_exist(): void
    {
        $this->assertTrue(File::exists(public_path('sw.js')), 'sw.js missing from /public');
        $this->assertTrue(File::exists(public_path('offline.html')), 'offline.html missing from /public');

        $sw = File::get(public_path('sw.js'));
        $this->assertStringContainsString('install', $sw);
        $this->assertStringContainsString('activate', $sw);
        $this->assertStringContainsString('fetch', $sw);
    }

    public function test_service_worker_uses_versioned_caches_and_update_flow(): void
    {
        $sw = File::get(public_path('sw.js'));

        // Versioned cache names so old ones get evicted on activate.
        $this->assertStringContainsString("VERSION = 'v3'", $sw);
        $this->assertStringContainsString('SHELL_CACHE', $sw);
        $this->assertStringContainsString('ASSET_CACHE', $sw);
        $this->assertStringContainsString('FONTS_CACHE', $sw);
        $this->assertStringContainsString('API_CACHE', $sw);

        // Precache list covers manifest, favicon, icons.
        $this->assertStringContainsString('/manifest.json', $sw);
        $this->assertStringContainsString('/favicon.ico', $sw);
        $this->assertStringContainsString('/icons/icon-512.png', $sw);

        // Read API: stale-while-revalidate for catalog/orders lists.
        $this->assertStringContainsString('/api/products', $sw);
        $this->assertStringContainsString('/api/categories', $sw);
        $this->assertStringContainsString('/api/orders', $sw);
        $this->assertStringContainsString('staleWhileRevalidate', $sw);

        // Google Fonts caching.
        $this->assertStringContainsString('fonts.googleapis.com', $sw);
        $this->assertStringContainsString('fonts.gstatic.com', $sw);

        // Update flow — message handler for SKIP_WAITING.
        $this->assertStringContainsString("addEventListener('message'", $sw);
        $this->assertStringContainsString('SKIP_WAITING', $sw);
    }

    public function test_pwa_icons_exist_for_all_required_sizes(): void
    {
        $required = [
            // Android — full size matrix.
            'icon-16.png', 'icon-32.png', 'icon-48.png', 'icon-96.png',
            'icon-144.png', 'icon-192.png', 'icon-512.png',
            'icon-512-maskable.png', 'icon-512-monochrome.png',
            // iOS touch icons.
            'apple-touch-icon-152.png', 'apple-touch-icon-167.png',
            'apple-touch-icon-180.png', 'apple-touch-icon.png',
            // App Store / Capacitor launcher asset.
            'icon-1024.png',
        ];
        foreach ($required as $icon) {
            $path = public_path('icons/'.$icon);
            $this->assertTrue(File::exists($path), "{$icon} missing from /public/icons");
        }
    }

    public function test_manifest_lists_monochrome_purpose_icon(): void
    {
        $manifest = json_decode(File::get(public_path('manifest.json')), true);
        $purposes = array_column($manifest['icons'], 'purpose');
        $this->assertContains('monochrome', $purposes);
        $this->assertContains('maskable', $purposes);

        // At least one 192 and one 512 icon present.
        $sizes = array_column($manifest['icons'], 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
    }

    public function test_icon_generator_command_produces_files(): void
    {
        $exit = \Artisan::call('pwa:icons');
        $this->assertSame(0, $exit);
        $this->assertTrue(File::exists(public_path('icons/icon-512.png')));
    }

    public function test_ios_splash_images_exist_for_known_devices(): void
    {
        $required = [
            'splash-750x1334.png', 'splash-1242x2208.png', 'splash-1125x2436.png',
            'splash-828x1792.png', 'splash-1170x2532.png', 'splash-1284x2778.png',
            'splash-1179x2556.png', 'splash-2556x2778.png',
            'splash-744x1133.png', 'splash-820x1180.png',
            'splash-834x1194.png', 'splash-1024x1366.png',
        ];
        foreach ($required as $splash) {
            $path = public_path('icons/splash/'.$splash);
            $this->assertTrue(File::exists($path), "{$splash} missing from /public/icons/splash");
        }
    }

    public function test_splash_generator_command_produces_files(): void
    {
        $exit = \Artisan::call('pwa:splash');
        $this->assertSame(0, $exit);
        $this->assertTrue(File::exists(public_path('icons/splash/splash-1170x2532.png')));
    }

    public function test_app_view_includes_apple_splash_links(): void
    {
        $response = $this->get('/login');
        $response->assertSee('apple-touch-startup-image', false);
        $response->assertSee('icons/splash/splash-1170x2532.png', false);
    }

    public function test_app_view_wires_up_manifest_and_service_worker(): void
    {
        $response = $this->get('/login');

        $response->assertSee('<link rel="manifest" href="', false);
        $response->assertSee('<meta name="theme-color" content="#212121">', false);
        $response->assertSee('<link rel="apple-touch-icon" href="', false);
        $response->assertSee('<meta name="apple-mobile-web-app-capable" content="yes">', false);
        $response->assertSee('navigator.serviceWorker.register(', false);
    }
}
