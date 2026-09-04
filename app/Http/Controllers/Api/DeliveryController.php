<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DeliveryEstimateException;
use App\Http\Controllers\Controller;
use App\Services\DeliveryEstimateService;
use App\Support\TenantSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    /**
     * Estimate delivery distance + fee for a destination address OR
     * exact pin coordinates (staff-allowed; never exposes the Maps API
     * key). Exactly one of {address, lat+lon} must be complete;
     * coordinates win when both are sent.
     */
    public function estimate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address' => 'required_without:lat,lon|string|max:500',
            'lat' => 'nullable|required_without:address|numeric|between:-90,90',
            'lon' => 'nullable|required_with:lat|numeric|between:-180,180',
        ]);

        $service = DeliveryEstimateService::forTenant($request->user()->tenant);

        try {
            $estimate = array_key_exists('lat', $validated) && array_key_exists('lon', $validated)
                ? $service->estimateFromCoords((float) $validated['lat'], (float) $validated['lon'])
                : $service->estimate($validated['address']);
        } catch (DeliveryEstimateException $e) {
            return response()->json(['reason' => $e->getMessage()], 422);
        }

        return response()->json($estimate);
    }

    /**
     * Reverse-geocode pin coordinates to an address string so the pin
     * picker can print the confirmed location into the address field
     * (staff-allowed).
     */
    public function reverse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lon' => 'required|numeric|between:-180,180',
        ]);

        try {
            $address = DeliveryEstimateService::forTenant($request->user()->tenant)
                ->reverseGeocode((float) $validated['lat'], (float) $validated['lon']);
        } catch (DeliveryEstimateException $e) {
            return response()->json(['reason' => $e->getMessage()], 422);
        }

        return response()->json(['address' => $address]);
    }

    /**
     * Fee configuration for manual-km fee preview (no key material).
     * `origin` echoes the stored store-origin pin (null when unset) so
     * the order form can center the map — no external calls here.
     */
    public function settings(): JsonResponse
    {
        $settings = TenantSettings::for();
        $originLat = $settings->deliveryOriginLat();
        $originLon = $settings->deliveryOriginLon();

        return response()->json([
            'fee_mode' => $settings->deliveryFeeMode(),
            'fee_per_km' => $settings->deliveryFeePerKm(),
            'min_fee' => $settings->deliveryMinFee(),
            'fixed_fee' => $settings->deliveryFixedFee(),
            'origin' => $originLat !== null && $originLon !== null
                ? ['lat' => $originLat, 'lon' => $originLon]
                : null,
        ]);
    }

    /**
     * Owner-only sample OSM estimate call (mirrors testMayar):
     * success + latency, or the OpenStreetMap error reason.
     */
    public function testConnection(Request $request): JsonResponse
    {
        if (! $request->user()->isOwner()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        try {
            $result = DeliveryEstimateService::forTenant($request->user()->tenant)
                ->testConnection();
        } catch (DeliveryEstimateException $e) {
            return response()->json([
                'connected' => false,
                'message' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'connected' => true,
            'message' => 'Koneksi OpenStreetMap berhasil.',
            'latency_ms' => $result['latency_ms'],
        ]);
    }
}
