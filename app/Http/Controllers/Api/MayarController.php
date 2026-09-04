<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\MayarService;
use App\Services\PaymentService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MayarController extends Controller
{
    public function __construct(private PaymentService $paymentService) {}

    /**
     * Create (or reuse a cached) Mayar dynamic QRIS for an order's remaining
     * amount. Available to any authenticated user who can view the order.
     */
    public function link(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $settings = \App\Support\TenantSettings::for($order->tenant_id);

        if (! $settings->mayarEnabled()) {
            return response()->json([
                'enabled' => false,
                'message' => 'QRIS Mayar belum aktif untuk tenant ini.',
            ]);
        }

        if ((float) $order->remaining_amount <= 0) {
            return response()->json(['enabled' => true, 'message' => 'Pesanan sudah lunas.'], 422);
        }

        try {
            $qr = MayarService::forTenant($order->tenant_id)->qrCodeForOrder($order);
        } catch (\Throwable $e) {
            Log::warning('Mayar link failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);

            return response()->json([
                'enabled' => true,
                'message' => 'QRIS tidak dapat dibuat saat ini.',
            ], 422);
        }

        return response()->json([
            'enabled' => true,
            'qr_url' => $qr['url'],
            'amount' => $qr['amount'],
        ]);
    }

    /**
     * Receive a Mayar webhook. Tenant is resolved from the URL slug since the
     * request has no auth/tenant middleware and Mayar cannot send custom
     * headers. Matching is amount + tenant heuristic; the Mayar transaction id
     * is stored as external_reference for replay-safe idempotency.
     */
    public function webhook(Request $request, string $tenantSlug): JsonResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->first();
        if (! $tenant) {
            return response()->json(['message' => 'Unknown tenant.'], 404);
        }

        // Bind tenant context so TenantScope / TenantSettings resolve correctly.
        app()->instance('currentTenantId', $tenant->id);

        $payload = $request->all();
        $data = $payload['data'] ?? $payload;

        $reference = $data['id'] ?? $data['transactionId'] ?? null;
        $amount = isset($data['amount']) ? (float) $data['amount'] : 0.0;

        // Log only correlation keys — never the full payload, which may carry
        // payer PII or card data.
        Log::info('Mayar webhook received', [
            'tenant' => $tenant->slug,
            'reference' => $reference,
            'amount' => $amount,
        ]);

        if (! $reference || $amount <= 0) {
            return response()->json(['message' => 'Ignored: no usable amount/reference.']);
        }

        // Idempotency: a Mayar transaction id should only be recorded once.
        if (\App\Models\Payment::where('external_reference', $reference)->exists()) {
            return response()->json(['message' => 'Duplicate webhook ignored.']);
        }

        // Match a pending, unpaid order in this tenant whose cached Mayar QR
        // amount equals the webhook amount. Mayar returns no link id, so amount
        // is the only correlation key. To prevent a forged (or misrouted) webhook
        // from paying the wrong order, require exactly ONE candidate — reject
        // ambiguous matches for manual review instead of guessing. Amount
        // comparison runs in SQL (ROUND works on both SQLite and MySQL);
        // remaining_amount > 0 is equivalent to grand_total > total_paid.
        $amountInt = (int) round($amount);
        $candidates = Order::query()
            ->where('status', OrderStatus::Pending)
            ->whereNotNull('mayar_qr_url')
            ->whereNotNull('mayar_amount')
            ->whereRaw('ROUND(mayar_amount) = ?', [$amountInt])
            ->whereColumn('grand_total', '>', 'total_paid')
            ->orderByDesc('id')
            ->get();

        if ($candidates->isEmpty()) {
            Log::info('Mayar webhook: no matching order', ['tenant' => $tenant->slug, 'amount' => $amount]);

            return response()->json(['message' => 'No matching order.']);
        }

        if ($candidates->count() > 1) {
            Log::warning('Mayar webhook: ambiguous order match', [
                'tenant' => $tenant->slug,
                'amount' => $amount,
                'reference' => $reference,
                'candidate_count' => $candidates->count(),
            ]);

            return response()->json(['message' => 'Ambiguous match — manual review required.'], 422);
        }

        $order = $candidates->first();

        try {
            $this->paymentService->addPayment($order, null, [
                'amount' => $amount,
                'metode' => PaymentMethod::Qris->value,
                'notes' => 'Mayar QRIS (ref '.$reference.')',
                'external_reference' => $reference,
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'Duplicate webhook ignored.']);
        }

        return response()->json(['message' => 'Payment recorded.']);
    }
}
