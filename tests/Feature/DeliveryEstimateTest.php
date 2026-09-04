<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliveryEstimateTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_estimate_distance_and_per_km_fee(): void
    {
        $tenant = $this->deliveryTenant([], ['fee_per_km' => 2500]);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::sequence()
                ->push($this->nominatimHit('-6.2000000', '106.8166667'))
                ->push($this->nominatimHit('-6.1751000', '106.8272000')),
            'router.project-osrm.org/*' => Http::response($this->osrmResponse(5205)),
        ]);

        // 5205 m -> (5205 + 5) / 10 floor = 521 hundredths -> 5.21 km
        // (half-up integer rounding); 5.21 km x Rp 2.500 = Rp 13.025.
        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['address' => 'Jl. Sudirman No. 10, Jakarta'])
            ->assertOk()
            ->assertJsonStructure(['distance_km', 'fee_amount', 'fee_mode', 'fee_per_km', 'min_fee', 'fixed_fee'])
            ->assertJson([
                'distance_km' => 5.21,
                'fee_amount' => 13025,
                'fee_mode' => 'per_km',
                'fee_per_km' => 2500,
                'min_fee' => 0,
                'fixed_fee' => 10000,
            ]);

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'router.project-osrm.org/route/v1/driving/')
                && str_contains($request->url(), '106.8166667,-6.2;106.8272,-6.1751')
                && str_contains($request->url(), 'overview=false');
        });

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), 'nominatim.openstreetmap.org/search')) {
                return false;
            }

            return $request->hasHeader('User-Agent', 'OrderTracker/1.0 (ongkir estimate)')
                && $request->hasHeader('Accept-Language', 'id')
                && str_contains($request->url(), 'countrycodes=id')
                && str_contains($request->url(), 'format=json')
                && str_contains($request->url(), 'limit=1');
        });
    }

    public function test_estimate_reuses_cached_origin_geocode(): void
    {
        $tenant = $this->deliveryTenant();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        $originGeocodes = 0;
        $destinationGeocodes = 0;
        $routes = 0;

        Http::fake(function (Request $request) use (&$originGeocodes, &$destinationGeocodes, &$routes) {
            $url = $request->url();

            if (str_contains($url, 'nominatim.openstreetmap.org/search')) {
                if (str_contains($url, 'Contoh')) {
                    $originGeocodes++;
                } else {
                    $destinationGeocodes++;
                }

                return Http::response($this->nominatimHit());
            }

            $routes++;

            return Http::response($this->osrmResponse(5205));
        });

        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['address' => 'Jl. Sudirman No. 10, Jakarta'])
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['address' => 'Jl. Sudirman No. 10, Jakarta'])
            ->assertOk();

        // First estimate: origin + destination geocode + route (3 calls).
        // Second: fully served from caches (origin ~30 days, destination
        // ~1 day, route ~24 hours) — zero further calls.
        $this->assertSame(1, $originGeocodes);
        $this->assertSame(1, $destinationGeocodes);
        $this->assertSame(1, $routes);
        Http::assertSentCount(3);
    }

    public function test_identical_estimates_fetch_the_osrm_route_only_once(): void
    {
        $tenant = $this->deliveryTenant();
        TenantSettings::for($tenant->id)->setDeliveryOrigin(-6.2, 106.8166667);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        $routes = 0;

        Http::fake(function (Request $request) use (&$routes) {
            if (str_contains($request->url(), 'router.project-osrm.org')) {
                $routes++;
            }

            return Http::response($this->osrmResponse(5205));
        });

        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['lat' => -6.1751, 'lon' => 106.8272])
            ->assertOk()
            ->assertJson(['distance_km' => 5.21]);

        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['lat' => -6.1751, 'lon' => 106.8272])
            ->assertOk()
            ->assertJson(['distance_km' => 5.21]);

        // The identical pin -> pin pair costs exactly one OSRM hit; the
        // second estimate is served from the ~24 hour route cache.
        $this->assertSame(1, $routes);
    }

    public function test_failed_osrm_route_is_not_cached_and_retries_http_after_the_negative_window(): void
    {
        $tenant = $this->deliveryTenant();
        TenantSettings::for($tenant->id)->setDeliveryOrigin(-6.2, 106.8166667);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        $attempts = 0;

        Http::fake(function (Request $request) use (&$attempts) {
            if (! str_contains($request->url(), 'router.project-osrm.org')) {
                return Http::response($this->nominatimHit());
            }

            $attempts++;

            return $attempts === 1
                ? Http::response(['error' => 'boom'], 500)
                : Http::response($this->osrmResponse(5205));
        });

        $reason = 'Gagal menghubungi layanan rute OpenStreetMap. Periksa koneksi internet lalu coba lagi.';

        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['lat' => -6.1751, 'lon' => 106.8272])
            ->assertUnprocessable()
            ->assertJson(['reason' => $reason]);
        $this->assertSame(1, $attempts);

        // The failure is not cached as a route: the immediate retry is
        // absorbed by the 60-second negative marker instead of the HTTP
        // layer, so a down OSRM is not hammered within one burst.
        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['lat' => -6.1751, 'lon' => 106.8272])
            ->assertUnprocessable()
            ->assertJson(['reason' => $reason]);
        $this->assertSame(1, $attempts);

        // Once the negative marker expires the route is refetched and
        // the estimate succeeds — no frozen outage.
        $this->travel(61)->seconds();

        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['lat' => -6.1751, 'lon' => 106.8272])
            ->assertOk()
            ->assertJson(['distance_km' => 5.21]);
        $this->assertSame(2, $attempts);
    }

    public function test_estimate_clamps_small_distance_to_min_fee(): void
    {
        $tenant = $this->deliveryTenant([], ['min_fee' => 10000]);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        Http::fake($this->osmFakes(1200));

        // 1.2 km x Rp 5.000 = Rp 6.000 -> clamped up to min_fee Rp 10.000.
        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['address' => 'Jl. Dekat No. 2'])
            ->assertOk()
            ->assertJson([
                'distance_km' => 1.2,
                'fee_amount' => 10000,
            ]);
    }

    public function test_estimate_fixed_mode_returns_fixed_fee(): void
    {
        $tenant = $this->deliveryTenant([], ['fee_mode' => 'fixed', 'fixed_fee' => 15000]);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        Http::fake($this->osmFakes(25400));

        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['address' => 'Jl. Jauh No. 99'])
            ->assertOk()
            ->assertJson([
                'distance_km' => 25.4,
                'fee_amount' => 15000,
                'fee_mode' => 'fixed',
                'fixed_fee' => 15000,
            ]);
    }

    public function test_estimate_requires_an_address(): void
    {
        $tenant = $this->deliveryTenant();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/delivery/estimate', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('address');
    }

    public function test_estimate_fails_without_tenant_address(): void
    {
        $tenant = Tenant::factory()->create(['address' => null]);

        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        Http::fake();

        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['address' => 'Jl. Sudirman No. 10, Jakarta'])
            ->assertUnprocessable()
            ->assertJson([
                'reason' => 'Alamat toko belum diatur. Atur alamat toko terlebih dahulu di Pengaturan → Profil.',
            ]);
    }

    public function test_estimate_fails_when_destination_address_is_not_found(): void
    {
        $tenant = $this->deliveryTenant();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        // Origin geocode resolves; destination geocode comes back empty.
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::sequence()
                ->push($this->nominatimHit())
                ->push([]),
            'router.project-osrm.org/*' => Http::response($this->osrmResponse(5205)),
        ]);

        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['address' => 'Pulau Terpencil'])
            ->assertUnprocessable()
            ->assertJson([
                'reason' => 'Alamat tidak ditemukan di OpenStreetMap. Perbaiki alamatnya atau isi jarak manual.',
            ]);
    }

    public function test_estimate_fails_when_nominatim_is_busy(): void
    {
        $tenant = $this->deliveryTenant();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response(['error' => 'rate limited'], 429),
            'router.project-osrm.org/*' => Http::response($this->osrmResponse(5205)),
        ]);

        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['address' => 'Jl. Sudirman No. 10, Jakarta'])
            ->assertUnprocessable()
            ->assertJson([
                'reason' => 'Layanan OpenStreetMap sedang sibuk. Coba lagi atau isi jarak manual.',
            ]);
    }

    public function test_estimate_fails_on_nominatim_http_error(): void
    {
        $tenant = $this->deliveryTenant();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response(['error' => 'boom'], 500),
            'router.project-osrm.org/*' => Http::response($this->osrmResponse(5205)),
        ]);

        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['address' => 'Jl. Sudirman No. 10, Jakarta'])
            ->assertUnprocessable()
            ->assertJson([
                'reason' => 'Gagal menghubungi layanan OpenStreetMap. Periksa koneksi internet lalu coba lagi.',
            ]);
    }

    public function test_estimate_fails_on_osrm_http_error(): void
    {
        $tenant = $this->deliveryTenant();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response($this->nominatimHit()),
            'router.project-osrm.org/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['address' => 'Jl. Sudirman No. 10, Jakarta'])
            ->assertUnprocessable()
            ->assertJson([
                'reason' => 'Gagal menghubungi layanan rute OpenStreetMap. Periksa koneksi internet lalu coba lagi.',
            ]);
    }

    public function test_estimate_fails_when_osrm_has_no_route(): void
    {
        $tenant = $this->deliveryTenant();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response($this->nominatimHit()),
            'router.project-osrm.org/*' => Http::response(['code' => 'NoRoute', 'routes' => []]),
        ]);

        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['address' => 'Alamat Searah Ujung Dunia'])
            ->assertUnprocessable()
            ->assertJson([
                'reason' => 'Rute tidak ditemukan antara alamat toko dan alamat tujuan. Perbaiki alamatnya atau isi jarak manual.',
            ]);
    }

    public function test_estimate_fails_gracefully_on_unexpected_response_shape(): void
    {
        $tenant = $this->deliveryTenant();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response(['error' => 'not a result list']),
            'router.project-osrm.org/*' => Http::response($this->osrmResponse(5205)),
        ]);

        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['address' => 'Jl. Sudirman No. 10, Jakarta'])
            ->assertUnprocessable()
            ->assertJson([
                'reason' => 'Respons OpenStreetMap tidak dikenali. Coba lagi.',
            ]);
    }

    public function test_staff_can_read_delivery_fee_settings_without_key_material(): void
    {
        $tenant = $this->deliveryTenant([], ['fee_per_km' => 6500, 'min_fee' => 10000]);

        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/delivery/settings')
            ->assertOk()
            ->assertJson([
                'fee_mode' => 'per_km',
                'fee_per_km' => 6500,
                'min_fee' => 10000,
                'fixed_fee' => 10000,
                'origin' => null,
            ]);

        $this->assertSame(
            ['fee_mode', 'fee_per_km', 'min_fee', 'fixed_fee', 'origin'],
            array_keys($response->json())
        );
    }

    public function test_staff_cannot_call_delivery_test_connection(): void
    {
        $tenant = $this->deliveryTenant();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/settings/delivery/test')
            ->assertForbidden();
    }

    public function test_owner_test_connection_success_returns_latency(): void
    {
        $tenant = $this->deliveryTenant();
        $owner = User::factory()->owner()->create(['tenant_id' => $tenant->id]);
        $token = $owner->createToken('test')->plainTextToken;

        Http::fake($this->osmFakes(0));

        $this->withToken($token)
            ->postJson('/api/settings/delivery/test')
            ->assertOk()
            ->assertJsonPath('connected', true)
            ->assertJsonPath('message', 'Koneksi OpenStreetMap berhasil.')
            ->assertJsonPath('latency_ms', fn ($latency) => is_int($latency) && $latency >= 0);
    }

    public function test_owner_test_connection_failure_surfaces_reason(): void
    {
        $tenant = $this->deliveryTenant();
        $owner = User::factory()->owner()->create(['tenant_id' => $tenant->id]);
        $token = $owner->createToken('test')->plainTextToken;

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response(['error' => 'boom'], 500),
            'router.project-osrm.org/*' => Http::response($this->osrmResponse(0)),
        ]);

        $this->withToken($token)
            ->postJson('/api/settings/delivery/test')
            ->assertOk()
            ->assertJson([
                'connected' => false,
                'message' => 'Gagal menghubungi layanan OpenStreetMap. Periksa koneksi internet lalu coba lagi.',
            ]);
    }

    public function test_staff_can_estimate_from_pin_coordinates_without_geocoding(): void
    {
        $tenant = $this->deliveryTenant([], ['fee_per_km' => 2500]);
        TenantSettings::for($tenant->id)->setDeliveryOrigin(-6.2, 106.8166667);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response($this->nominatimHit()),
            'router.project-osrm.org/*' => Http::response($this->osrmResponse(5205)),
        ]);

        // 5205 m -> 5.21 km x Rp 2.500 = Rp 13.025 (pin -> pin).
        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['lat' => -6.1751, 'lon' => 106.8272])
            ->assertOk()
            ->assertJson([
                'distance_km' => 5.21,
                'fee_amount' => 13025,
                'fee_mode' => 'per_km',
                'fee_per_km' => 2500,
            ]);

        // The OSRM route is the ONLY outbound request — zero Nominatim.
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'router.project-osrm.org/route/v1/driving/106.8166667,-6.2;106.8272,-6.1751')
                && str_contains($request->url(), 'overview=false');
        });
        Http::assertNotSent(function (Request $request) {
            return str_contains($request->url(), 'nominatim.openstreetmap.org');
        });
    }

    public function test_origin_pin_skips_origin_geocode_for_address_estimates(): void
    {
        $tenant = $this->deliveryTenant();
        TenantSettings::for($tenant->id)->setDeliveryOrigin(-6.2, 106.8166667);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        $originGeocodes = 0;
        $destinationGeocodes = 0;

        Http::fake(function (Request $request) use (&$originGeocodes, &$destinationGeocodes) {
            $url = $request->url();

            if (str_contains($url, 'nominatim.openstreetmap.org/search')) {
                if (str_contains($url, 'Contoh')) {
                    $originGeocodes++;
                } else {
                    $destinationGeocodes++;
                }

                return Http::response($this->nominatimHit('-6.1751000', '106.8272000'));
            }

            return Http::response($this->osrmResponse(5205));
        });

        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['address' => 'Jl. Sudirman No. 10, Jakarta'])
            ->assertOk()
            ->assertJson(['distance_km' => 5.21]);

        // Only the destination geocodes; the route starts at the pin.
        $this->assertSame(0, $originGeocodes);
        $this->assertSame(1, $destinationGeocodes);

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'router.project-osrm.org/route/v1/driving/106.8166667,-6.2;106.8272,-6.1751');
        });
    }

    public function test_estimate_from_coords_falls_back_to_geocoded_origin(): void
    {
        $tenant = $this->deliveryTenant(['address' => 'Jl. Asal Koordinat No. 3, Bandung']);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response($this->nominatimHit()),
            'router.project-osrm.org/*' => Http::response($this->osrmResponse(5205)),
        ]);

        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['lat' => -6.1751, 'lon' => 106.8272])
            ->assertOk()
            ->assertJson(['distance_km' => 5.21]);

        // Without a pin the origin geocodes once (unique address, so no
        // cross-test cache hit); the destination coords skip geocoding.
        Http::assertSentCount(2);
        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'nominatim.openstreetmap.org/search');
        });
        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'router.project-osrm.org/route/v1/driving/106.8166667,-6.2;106.8272,-6.1751');
        });
    }

    public function test_reverse_geocode_returns_display_name_for_pin(): void
    {
        $tenant = $this->deliveryTenant();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'place_id' => 987654,
                'display_name' => 'Jl. Sudirman No. 10, RT 1, Jakarta, Indonesia',
            ]),
        ]);

        $this->withToken($token)
            ->postJson('/api/delivery/reverse', ['lat' => -6.1751, 'lon' => 106.8272])
            ->assertOk()
            ->assertJson(['address' => 'Jl. Sudirman No. 10, RT 1, Jakarta, Indonesia']);

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'nominatim.openstreetmap.org/reverse')
                && str_contains($request->url(), 'lat=-6.1751')
                && str_contains($request->url(), 'lon=106.8272')
                && str_contains($request->url(), 'format=jsonv2')
                && str_contains($request->url(), 'zoom=18')
                && str_contains($request->url(), 'accept-language=id')
                && $request->hasHeader('User-Agent', 'OrderTracker/1.0 (ongkir estimate)');
        });
    }

    public function test_reverse_geocode_returns_reason_when_location_is_unknown(): void
    {
        $tenant = $this->deliveryTenant();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        // Nominatim /reverse answers 200 with an error payload when it
        // cannot place the coordinates.
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response(['error' => 'Unable to geocode']),
        ]);

        $this->withToken($token)
            ->postJson('/api/delivery/reverse', ['lat' => -6.1751, 'lon' => 106.8272])
            ->assertUnprocessable()
            ->assertJson([
                'reason' => 'Lokasi pin tidak ditemukan di OpenStreetMap. Geser pin ke lokasi lain atau tulis alamatnya secara manual.',
            ]);
    }

    public function test_reverse_geocode_returns_reason_when_nominatim_is_busy(): void
    {
        $tenant = $this->deliveryTenant();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response(['error' => 'rate limited'], 429),
        ]);

        $this->withToken($token)
            ->postJson('/api/delivery/reverse', ['lat' => -6.1751, 'lon' => 106.8272])
            ->assertUnprocessable()
            ->assertJson([
                'reason' => 'Layanan OpenStreetMap sedang sibuk. Coba lagi atau isi jarak manual.',
            ]);
    }

    public function test_estimate_rejects_latitude_without_longitude(): void
    {
        $tenant = $this->deliveryTenant();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['lat' => -6.1751])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lon', 'address']);
    }

    public function test_estimate_rejects_out_of_range_coordinates(): void
    {
        $tenant = $this->deliveryTenant();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['lat' => -90.5, 'lon' => 106.8272])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lat');

        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['lat' => -6.1751, 'lon' => 181])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lon');
    }

    public function test_delivery_settings_echo_the_stored_origin_pin(): void
    {
        $tenant = $this->deliveryTenant();
        TenantSettings::for($tenant->id)->setDeliveryOrigin(-6.2, 106.8166667);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/delivery/settings')
            ->assertOk()
            ->assertJson([
                'origin' => ['lat' => -6.2, 'lon' => 106.8166667],
            ]);
    }

    /**
     * Tenant with a delivery origin address; delivery settings can be
     * overridden per test. No Maps key is stored — the OSM engine is
     * keyless.
     */
    private function deliveryTenant(array $tenantAttributes = [], array $delivery = []): Tenant
    {
        $tenant = Tenant::factory()->create(array_merge([
            'address' => 'Jl. Contoh No. 1, Jakarta',
        ], $tenantAttributes));

        if ($delivery !== []) {
            TenantSettings::for($tenant->id)->updateDelivery($delivery);
        }

        return $tenant;
    }

    /**
     * Standard fake pair: geocodes resolve to the default coords and
     * OSRM returns the given driving meters.
     */
    private function osmFakes(int $meters): array
    {
        return [
            'nominatim.openstreetmap.org/*' => Http::response($this->nominatimHit()),
            'router.project-osrm.org/*' => Http::response($this->osrmResponse($meters)),
        ];
    }

    /**
     * Nominatim search payload: a single hit with lat/lon as JSON
     * strings, exactly as the public API returns them.
     */
    private function nominatimHit(string $lat = '-6.2000000', string $lon = '106.8166667'): array
    {
        return [
            [
                'place_id' => 123456,
                'display_name' => 'Jl. Contoh No. 1, Jakarta, Indonesia',
                'lat' => $lat,
                'lon' => $lon,
            ],
        ];
    }

    /**
     * OSRM route payload: routes.0.distance carries the driving meters.
     */
    private function osrmResponse(int $meters): array
    {
        return [
            'code' => 'Ok',
            'routes' => [
                ['distance' => $meters, 'duration' => 620.5],
            ],
            'waypoints' => [],
        ];
    }
}
