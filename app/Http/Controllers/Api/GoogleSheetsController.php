<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GoogleOAuthService;
use App\Services\GoogleSheetsService;
use App\Services\OrdersImportService;
use App\Support\TenantSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoogleSheetsController extends Controller
{
    public function connect(GoogleOAuthService $oauth): JsonResponse
    {
        $tenantId = (int) app('currentTenantId');

        return response()->json([
            'url' => $oauth->getConnectUrl($tenantId),
        ]);
    }

    public function callback(Request $request, GoogleOAuthService $oauth): RedirectResponse
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');

        $fallback = rtrim((string) config('app.url'), '/').'/settings/ops';

        if (is_string($error) && $error !== '') {
            return redirect($fallback.'?google=error&message='.urlencode($error));
        }

        if (! is_string($code) || $code === '' || ! is_string($state) || $state === '') {
            return redirect($fallback.'?google=error&message='.urlencode('Missing OAuth code or state'));
        }

        try {
            $result = $oauth->handleCallback($code, $state);
            $tenant = $result['tenant'];

            return redirect(rtrim((string) config('app.url'), '/').'/settings/ops?google=connected');
        } catch (\Throwable $e) {
            return redirect($fallback.'?google=error&message='.urlencode($e->getMessage()));
        }
    }

    public function disconnect(GoogleOAuthService $oauth): JsonResponse
    {
        $oauth->disconnect((int) app('currentTenantId'));

        return response()->json(TenantSettings::for()->integrationsToArray());
    }

    public function import(
        OrdersImportService $importService,
        GoogleSheetsService $sheets,
    ): JsonResponse {
        $tenantId = (int) app('currentTenantId');
        $settings = TenantSettings::for($tenantId);
        $spreadsheetId = $settings->sheetsSpreadsheetId();

        if (! $settings->googleConnected()) {
            return response()->json(['message' => 'Google Sheets is not connected.'], 422);
        }

        if (! $spreadsheetId) {
            return response()->json(['message' => 'Spreadsheet ID is required.'], 422);
        }

        $owner = User::query()
            ->where('tenant_id', $tenantId)
            ->where('role', 'owner')
            ->firstOrFail();

        $productRows = $sheets->readTab($tenantId, $spreadsheetId, $settings->sheetsProductsTab());
        $orderRows = $sheets->readTab($tenantId, $spreadsheetId, $settings->sheetsOrdersTab());

        $products = $importService->importProductsRows($productRows, $tenantId);
        $orders = $importService->importOrdersRows($orderRows, $owner, 2, $tenantId);

        return response()->json([
            'products' => $products,
            'orders' => $orders,
            'total' => ($products['total'] ?? 0) + ($orders['total'] ?? 0),
            'success' => ($products['success'] ?? 0) + ($orders['success'] ?? 0),
            'failed' => ($products['failed'] ?? 0) + ($orders['failed'] ?? 0),
            'skipped' => ($orders['skipped'] ?? 0),
        ]);
    }

}
