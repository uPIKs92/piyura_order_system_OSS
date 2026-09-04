<?php

namespace App\Services;

use App\Exceptions\DeliveryEstimateException;
use App\Models\Tenant;
use App\Support\TenantSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Delivery (ongkir) distance + fee estimation via public OpenStreetMap
 * services: Nominatim geocoding (address -> coords, Indonesia-only, no
 * API key; plus /reverse for coords -> address text) + the OSRM demo
 * router (coords -> driving meters). A stored tenant origin pin (see
 * TenantSettings::setDeliveryOrigin) short-circuits origin geocoding:
 * estimates and testConnection() then route pin -> destination. All
 * external calls are backend-only; the tenant origin geocode is cached
 * ~30 days and destination geocodes ~1 day per tenant + address hash
 * (public Nominatim policy allows only ~1 request/second per source
 * IP, so repeated estimates of the same address must not re-geocode).
 * OSRM route legs are cached ~24 hours per coordinate pair (the road
 * network is effectively static); failed fetches are never cached as
 * a route but leave a 60-second negative marker so a down OSRM is not
 * hammered by a burst of retries.
 * Fees are resolved from current tenant settings in integer-cents
 * half-up math (mirrors OrderService/PaymentService). The previous
 * Google Distance Matrix engine is kept dormant in fetchMetersGoogle().
 */
class DeliveryEstimateService
{
    private const NOMINATIM_SEARCH_URL = 'https://nominatim.openstreetmap.org/search';

    private const NOMINATIM_REVERSE_URL = 'https://nominatim.openstreetmap.org/reverse';

    private const OSRM_ROUTE_URL = 'https://router.project-osrm.org/route/v1/driving';

    /** Nominatim usage policy requires a descriptive User-Agent. */
    private const USER_AGENT = 'OrderTracker/1.0 (ongkir estimate)';

    private const DISTANCE_MATRIX_URL = 'https://maps.googleapis.com/maps/api/distancematrix/json';

    /** @var array<string, string> Google status -> user-facing reason. */
    private const STATUS_REASONS = [
        'REQUEST_DENIED' => 'Google Maps menolak permintaan. Periksa API key dan pastikan Distance Matrix API sudah diaktifkan.',
        'OVER_QUERY_LIMIT' => 'Kuota Google Maps sudah terlampaui. Coba lagi nanti.',
        'ZERO_RESULTS' => 'Rute tidak ditemukan antara alamat toko dan alamat tujuan.',
        'NOT_FOUND' => 'Alamat tidak dikenali oleh Google Maps. Perbaiki alamatnya lalu coba lagi.',
        'INVALID_REQUEST' => 'Permintaan Google Maps tidak valid. Periksa alamat toko dan alamat tujuan.',
        'UNKNOWN_ERROR' => 'Google Maps sedang bermasalah. Coba lagi.',
    ];

    private const UNEXPECTED_SHAPE_REASON = 'Respons Google Maps tidak dikenali. Coba lagi.';

    private const OSM_UNEXPECTED_SHAPE_REASON = 'Respons OpenStreetMap tidak dikenali. Coba lagi.';

    public function __construct(private Tenant $tenant, private TenantSettings $settings) {}

    public static function forTenant(Tenant $tenant): self
    {
        return new self($tenant, TenantSettings::for($tenant->id));
    }

    /**
     * Estimate driving distance to a destination address and resolve the
     * delivery fee from current settings.
     *
     * @return array{distance_km: float, fee_amount: float, fee_mode: string, fee_per_km: float, min_fee: float, fixed_fee: float}
     *
     * @throws DeliveryEstimateException with a user-facing reason.
     */
    public function estimate(string $destination): array
    {
        return $this->estimateForKm($this->distanceKm($destination));
    }

    /**
     * Estimate driving distance to exact destination coordinates (a
     * customer pin) and resolve the fee — no destination geocoding on
     * this path.
     *
     * @return array{distance_km: float, fee_amount: float, fee_mode: string, fee_per_km: float, min_fee: float, fixed_fee: float}
     *
     * @throws DeliveryEstimateException with a user-facing reason.
     */
    public function estimateFromCoords(float $lat, float $lon): array
    {
        $origin = $this->originCoords();
        $km = $this->metersToKm($this->osrmRouteMeters($origin, ['lat' => $lat, 'lon' => $lon]));

        return $this->estimateForKm($km);
    }

    /**
     * @return array{distance_km: float, fee_amount: float, fee_mode: string, fee_per_km: float, min_fee: float, fixed_fee: float}
     */
    private function estimateForKm(float $km): array
    {
        return [
            'distance_km' => $km,
            'fee_amount' => $this->feeForKm($km),
            'fee_mode' => $this->settings->deliveryFeeMode(),
            'fee_per_km' => $this->settings->deliveryFeePerKm(),
            'min_fee' => $this->settings->deliveryMinFee(),
            'fixed_fee' => $this->settings->deliveryFixedFee(),
        ];
    }

    /**
     * Resolve the delivery fee for a known distance under current
     * settings: per_km -> max(rate x km, min_fee); fixed -> fixed_fee.
     */
    public function feeForKm(float $km): float
    {
        if ($this->settings->deliveryFeeMode() === 'fixed') {
            return $this->toAmount($this->toCents($this->settings->deliveryFixedFee()));
        }

        $kmHundredths = max(0, (int) round($km * 100));
        $perKmCents = $this->toCents($this->settings->deliveryFeePerKm());

        // Exact integer math with a +50 bias so the /100 rounds half-up.
        $feeCents = (int) floor(($perKmCents * $kmHundredths + 50) / 100);

        return $this->toAmount(max($feeCents, $this->toCents($this->settings->deliveryMinFee())));
    }

    /**
     * Sample connectivity check (no side effects): with an origin pin
     * set, one OSRM route pin -> pin (geocoding skipped entirely);
     * otherwise one cached-origin Nominatim geocode + one OSRM route
     * from the tenant address to itself.
     *
     * @return array{latency_ms: int, distance_km: float}
     *
     * @throws DeliveryEstimateException with a user-facing reason.
     */
    public function testConnection(): array
    {
        $start = microtime(true);
        $origin = $this->originCoords();
        $meters = $this->osrmRouteMeters($origin, $origin);
        $latencyMs = (int) round((microtime(true) - $start) * 1000);

        return [
            'latency_ms' => $latencyMs,
            'distance_km' => $this->metersToKm($meters),
        ];
    }

    /**
     * @throws DeliveryEstimateException when the tenant address is empty.
     */
    private function requireOriginAddress(): string
    {
        $origin = trim((string) $this->tenant->address);

        if ($origin === '') {
            throw new DeliveryEstimateException(
                'Alamat toko belum diatur. Atur alamat toko terlebih dahulu di Pengaturan → Profil.'
            );
        }

        return $origin;
    }

    /**
     * @throws DeliveryEstimateException with a user-facing reason.
     */
    private function distanceKm(string $destination): float
    {
        $origin = $this->originCoords();
        $destination = $this->destinationGeo($destination);

        return $this->metersToKm($this->osrmRouteMeters($origin, $destination));
    }

    /**
     * Tenant origin coordinates: the stored origin pin when set, else
     * the ~30-day cached geocode of the tenant address.
     *
     * @return array{lat: float, lon: float}
     *
     * @throws DeliveryEstimateException with a user-facing reason.
     */
    private function originCoords(): array
    {
        $lat = $this->settings->deliveryOriginLat();
        $lon = $this->settings->deliveryOriginLon();

        if ($lat !== null && $lon !== null) {
            return ['lat' => $lat, 'lon' => $lon];
        }

        return $this->originGeo($this->requireOriginAddress());
    }

    /**
     * Tenant origin geocode, cached ~30 days per tenant + address hash,
     * so a typical estimate costs one destination geocode + one route.
     *
     * @return array{lat: float, lon: float}
     */
    private function originGeo(string $origin): array
    {
        return Cache::remember(
            "delivery.origin_geo.{$this->tenant->id}.".md5($origin),
            now()->addDays(30),
            fn () => $this->geocode($origin),
        );
    }

    /**
     * Destination geocode, cached ~1 day per tenant + address hash, so
     * repeated estimates of the same address cost zero Nominatim calls
     * (public Nominatim usage policy allows ~1 request/second). A
     * failed geocode throws and is never cached.
     *
     * @return array{lat: float, lon: float}
     *
     * @throws DeliveryEstimateException with a user-facing reason.
     */
    private function destinationGeo(string $destination): array
    {
        return Cache::remember(
            "delivery.dest_geo.{$this->tenant->id}.".md5($destination),
            now()->addDay(),
            fn () => $this->geocode($destination),
        );
    }

    /**
     * Geocode an address to coordinates via public Nominatim
     * (Indonesia-restricted). Nominatim returns lat/lon as JSON strings.
     *
     * @return array{lat: float, lon: float}
     *
     * @throws DeliveryEstimateException with a user-facing reason.
     */
    private function geocode(string $address): array
    {
        $response = Http::timeout(10)
            ->withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept-Language' => 'id',
            ])
            ->get(self::NOMINATIM_SEARCH_URL, [
                'q' => $address,
                'format' => 'json',
                'limit' => 1,
                'countrycodes' => 'id',
            ]);

        if ($response->status() === 429) {
            throw new DeliveryEstimateException(
                'Layanan OpenStreetMap sedang sibuk. Coba lagi atau isi jarak manual.'
            );
        }

        if ($response->failed()) {
            throw new DeliveryEstimateException(
                'Gagal menghubungi layanan OpenStreetMap. Periksa koneksi internet lalu coba lagi.'
            );
        }

        $results = $response->json();

        if (! is_array($results)) {
            throw new DeliveryEstimateException(self::OSM_UNEXPECTED_SHAPE_REASON);
        }

        if ($results === []) {
            throw new DeliveryEstimateException(
                'Alamat tidak ditemukan di OpenStreetMap. Perbaiki alamatnya atau isi jarak manual.'
            );
        }

        $hit = $results[0] ?? null;

        if (
            ! is_array($hit)
            || ! is_numeric($hit['lat'] ?? null)
            || ! is_numeric($hit['lon'] ?? null)
        ) {
            throw new DeliveryEstimateException(self::OSM_UNEXPECTED_SHAPE_REASON);
        }

        return ['lat' => (float) $hit['lat'], 'lon' => (float) $hit['lon']];
    }

    /**
     * Reverse-geocode coordinates to a display address via public
     * Nominatim (format=jsonv2, zoom=18, Indonesian labels) — used to
     * print a confirmed pin back into the address field.
     *
     * @throws DeliveryEstimateException with a user-facing reason.
     */
    public function reverseGeocode(float $lat, float $lon): string
    {
        $response = Http::timeout(10)
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->get(self::NOMINATIM_REVERSE_URL, [
                'lat' => $lat,
                'lon' => $lon,
                'format' => 'jsonv2',
                'zoom' => 18,
                'accept-language' => 'id',
            ]);

        if ($response->status() === 429) {
            throw new DeliveryEstimateException(
                'Layanan OpenStreetMap sedang sibuk. Coba lagi atau isi jarak manual.'
            );
        }

        if ($response->failed()) {
            throw new DeliveryEstimateException(
                'Gagal menghubungi layanan OpenStreetMap. Periksa koneksi internet lalu coba lagi.'
            );
        }

        $displayName = $response->json('display_name');

        if (! is_string($displayName) || trim($displayName) === '') {
            throw new DeliveryEstimateException(
                'Lokasi pin tidak ditemukan di OpenStreetMap. Geser pin ke lokasi lain atau tulis alamatnya secara manual.'
            );
        }

        return $displayName;
    }

    /**
     * Driving distance in meters between two coordinates via the public
     * OSRM demo server (coords joined in lon,lat order). Successful legs
     * are cached ~24 hours per coordinate pair; a failed fetch is never
     * cached as a route but leaves a 60-second negative marker that
     * re-throws its user-facing reason without another HTTP attempt.
     *
     * @param  array{lat: float, lon: float}  $origin
     * @param  array{lat: float, lon: float}  $dest
     *
     * @throws DeliveryEstimateException with a user-facing reason.
     */
    private function osrmRouteMeters(array $origin, array $dest): int
    {
        $key = 'osrm:route:'.sha1("{$origin['lat']},{$origin['lon']}|{$dest['lat']},{$dest['lon']}");
        $failKey = $key.'.fail';

        $failReason = Cache::get($failKey);

        if (is_string($failReason) && $failReason !== '') {
            throw new DeliveryEstimateException($failReason);
        }

        return Cache::remember($key, now()->addDay(), function () use ($origin, $dest, $failKey): int {
            try {
                return $this->fetchOsrmRouteMeters($origin, $dest);
            } catch (DeliveryEstimateException $exception) {
                Cache::put($failKey, $exception->getMessage(), now()->addMinute());

                throw $exception;
            }
        });
    }

    /**
     * One OSRM route fetch; every failure mode becomes a
     * DeliveryEstimateException carrying a user-facing reason.
     *
     * @param  array{lat: float, lon: float}  $origin
     * @param  array{lat: float, lon: float}  $dest
     *
     * @throws DeliveryEstimateException with a user-facing reason.
     */
    private function fetchOsrmRouteMeters(array $origin, array $dest): int
    {
        $url = sprintf(
            '%s/%s,%s;%s,%s',
            self::OSRM_ROUTE_URL,
            $origin['lon'],
            $origin['lat'],
            $dest['lon'],
            $dest['lat'],
        );

        $response = Http::timeout(10)
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->get($url, ['overview' => 'false']);

        if ($response->failed()) {
            throw new DeliveryEstimateException(
                'Gagal menghubungi layanan rute OpenStreetMap. Periksa koneksi internet lalu coba lagi.'
            );
        }

        $routes = $response->json('routes');

        if (! is_array($routes) || $routes === []) {
            throw new DeliveryEstimateException(
                'Rute tidak ditemukan antara alamat toko dan alamat tujuan. Perbaiki alamatnya atau isi jarak manual.'
            );
        }

        $meters = is_array($routes[0]) ? ($routes[0]['distance'] ?? null) : null;

        if (! is_numeric($meters) || (int) $meters < 0) {
            throw new DeliveryEstimateException(self::OSM_UNEXPECTED_SHAPE_REASON);
        }

        return (int) $meters;
    }

    /**
     * Dormant Google fallback — re-enable by calling this from
     * distanceKm() and restoring the key UI in SettingsOps (see
     * docs/features/13-delivery-fee.md).
     *
     * One Distance Matrix call (mode=driving, region=id) returning the
     * driving distance in meters.
     *
     * @throws DeliveryEstimateException with a user-facing reason on
     *                                   configuration, network, or Google errors.
     */
    private function fetchMetersGoogle(string $origin, string $destination): int
    {
        $apiKey = $this->settings->googleMapsApiKey();
        if (! $apiKey) {
            throw new DeliveryEstimateException(
                'Google Maps API key belum diatur. Masukkan jarak secara manual, atau minta pemilik toko mengatur API key di Pengaturan.'
            );
        }

        $response = Http::timeout(15)->get(self::DISTANCE_MATRIX_URL, [
            'origins' => $origin,
            'destinations' => $destination,
            'mode' => 'driving',
            'region' => 'id',
            'key' => $apiKey,
        ]);

        if ($response->failed()) {
            throw new DeliveryEstimateException(
                'Gagal menghubungi layanan Google Maps. Periksa koneksi internet lalu coba lagi.'
            );
        }

        $this->assertStatus($response->json('status'));

        $elementStatus = $response->json('rows.0.elements.0.status');
        $this->assertStatus(is_string($elementStatus) ? $elementStatus : '');

        $meters = $response->json('rows.0.elements.0.distance.value');
        if (! is_numeric($meters) || (int) $meters < 0) {
            throw new DeliveryEstimateException(self::UNEXPECTED_SHAPE_REASON);
        }

        return (int) $meters;
    }

    /**
     * Meters -> km rounded half-up to 2 dp in exact integer math
     * ((meters + 5) / 10 floor -> hundredths of a km).
     */
    private function metersToKm(int $meters): float
    {
        return round((int) floor(($meters + 5) / 10) / 100, 2);
    }

    /**
     * @throws DeliveryEstimateException unless the Distance Matrix status is OK.
     */
    private function assertStatus(mixed $status): void
    {
        if ($status === 'OK') {
            return;
        }

        throw new DeliveryEstimateException(
            is_string($status) && isset(self::STATUS_REASONS[$status])
                ? self::STATUS_REASONS[$status]
                : self::UNEXPECTED_SHAPE_REASON
        );
    }

    /**
     * Convert a monetary value into integer cents. PHP round() is
     * half-away-from-zero, i.e. half-up for the non-negative money
     * values used here (mirrors OrderService/PaymentService).
     */
    private function toCents(float|string|int|null $value): int
    {
        return (int) round((float) $value * 100);
    }

    /**
     * Render integer cents back to a decimal amount; cents are exact
     * so no further rounding occurs.
     */
    private function toAmount(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
