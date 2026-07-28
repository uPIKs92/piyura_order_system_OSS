<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $paymentService) {}

    public function index(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return response()->json($order->payments()->latest('paid_at')->get());
    }

    public function store(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'metode' => 'required|in:cash,transfer,qris',
            'paid_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $payment = $this->paymentService->addPayment($order, $request->user(), $validated);

        return response()->json($payment, 201);
    }

    public function destroy(Request $request, Order $order, Payment $payment): JsonResponse
    {
        abort_unless($payment->order_id === $order->id, 404);
        $this->authorize('delete', $payment);

        $payment->delete();
        $order->syncTotalPaid();

        return response()->json(['message' => 'Payment deleted.']);
    }
}
