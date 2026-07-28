<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ThemePalettes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TenantSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        return response()->json($tenant->toBrandingArray());
    }

    public function update(Request $request): JsonResponse
    {
        if (! $request->user()->isOwner()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'tagline' => 'nullable|string|max:120',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'invoice_footer_text' => 'nullable|string|max:500',
        ]);

        $tenant = $request->user()->tenant;
        $tenant->update($validated);

        return response()->json($tenant->fresh()->toBrandingArray());
    }

    public function updateAppearance(Request $request): JsonResponse
    {
        if (! $request->user()->isOwner()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'theme_mode' => 'required|in:light,dark,system',
            'theme_palette' => ['required', ThemePalettes::validationRule()],
        ]);

        $tenant = $request->user()->tenant;
        $tenant->update($validated);

        return response()->json($tenant->fresh()->toBrandingArray());
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        if (! $request->user()->isOwner()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'logo' => 'required|file|max:2048',
        ]);

        $tenant = $request->user()->tenant;
        $file = $request->file('logo');
        $allowed = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
        ];
        $mime = $file->getMimeType() ?: '';
        if (! isset($allowed[$mime])) {
            return response()->json(['message' => 'Invalid logo file type.'], 422);
        }

        $ext = $allowed[$mime];
        $path = "tenants/{$tenant->id}/logo.{$ext}";

        if ($tenant->logo_path) {
            Storage::disk('public')->delete($tenant->logo_path);
        }

        $file->storeAs("tenants/{$tenant->id}", "logo.{$ext}", 'public');
        $tenant->update(['logo_path' => $path]);

        return response()->json($tenant->fresh()->toBrandingArray());
    }

    public function deleteLogo(Request $request): JsonResponse
    {
        if (! $request->user()->isOwner()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $tenant = $request->user()->tenant;

        if ($tenant->logo_path) {
            Storage::disk('public')->delete($tenant->logo_path);
            $tenant->update(['logo_path' => null]);
        }

        return response()->json($tenant->fresh()->toBrandingArray());
    }

    public function app(): JsonResponse
    {
        return response()->json([
            'app_name' => config('branding.app_name'),
            'platform_name' => config('branding.platform_name'),
            'show_platform_credit_on_invoice' => config('branding.show_platform_credit_on_invoice'),
        ]);
    }
}
