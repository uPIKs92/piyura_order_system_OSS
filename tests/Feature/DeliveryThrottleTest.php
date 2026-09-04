<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliveryThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_estimate_requests_are_throttled_to_ten_per_minute(): void
    {
        $tenant = $this->deliveryTenant();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        // Feature tests run all requests in one process, and the first
        // request's Authenticate middleware switches the shared auth
        // manager's default guard to sanctum mid-loop. Pin it upfront
        // so the limiter resolves the same by-key (user id) for every
        // request instead of splitting hits between IP and user keys.
        $this->app->make('auth')->shouldUse('sanctum');

        Http::fake($this->osmFakes());

        for ($i = 0; $i < 10; $i++) {
            $this->withToken($token)
                ->postJson('/api/delivery/estimate', ['address' => 'Jl. Sudirman No. 10, Jakarta'])
                ->assertOk();
        }

        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['address' => 'Jl. Sudirman No. 10, Jakarta'])
            ->assertStatus(429);
    }

    public function test_destination_geocode_is_cached_for_repeated_estimates(): void
    {
        $tenant = $this->deliveryTenant();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('test')->plainTextToken;

        $destinationGeocodes = 0;

        Http::fake(function (Request $request) use (&$destinationGeocodes) {
            $url = $request->url();

            if (str_contains($url, 'nominatim.openstreetmap.org/search')) {
                // The tenant origin address ("Jl. Contoh ...") is the
                // origin geocode; anything else is a destination hit.
                if (! str_contains($url, 'Contoh')) {
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

        $this->withToken($token)
            ->postJson('/api/delivery/estimate', ['address' => 'Jl. Sudirman No. 10, Jakarta'])
            ->assertOk()
            ->assertJson(['distance_km' => 5.21]);

        // The same destination address geocodes once; the second
        // estimate is served from the per-tenant ~1 day cache.
        $this->assertSame(1, $destinationGeocodes);
    }

    private function deliveryTenant(): Tenant
    {
        return Tenant::factory()->create([
            'address' => 'Jl. Contoh No. 1, Jakarta',
        ]);
    }

    /**
     * Standard fake pair: geocodes resolve to the default coords and
     * OSRM returns driving meters.
     *
     * @return array<string, Response>
     */
    private function osmFakes(): array
    {
        return [
            'nominatim.openstreetmap.org/*' => Http::response($this->nominatimHit()),
            'router.project-osrm.org/*' => Http::response($this->osrmResponse(5205)),
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
