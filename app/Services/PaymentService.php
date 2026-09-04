<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function addPayment(Order $order, ?User $user, array $data): Payment
    {
        $payment = DB::transaction(function () use ($order, $user, $data) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            $requestedCents = $this->toCents($data['amount']);
            $tenderedCents = array_key_exists('tendered', $data) && $data['tendered'] !== null
                ? $this->toCents($data['tendered'])
                : $requestedCents;

            $grandTotalCents = $this->toCents($order->grand_total);
            $alreadyPaidCents = $this->toCents($order->total_paid);
            $remainingCents = max(0, $grandTotalCents - $alreadyPaidCents);

            // Cash tendered beyond what's still owed is change/refund owed to
            // buyer. Must use tendered (the money actually handed over), not
            // the recorded amount: the form records amount = remaining while
            // tendered carries the overpay (e.g. total 27.750, cash 100.000
            // → amount 27.750, change 72.250).
            $changeCents = max(0, $tenderedCents - $remainingCents);

            $payment = Payment::create([
                'order_id' => $order->id,
                'amount' => $this->toAmount($requestedCents),
                'tendered' => $this->toAmount($tenderedCents),
                'change_amount' => $this->toAmount($changeCents),
                'metode' => PaymentMethod::from($data['metode']),
                'paid_at' => $data['paid_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
                'external_reference' => $data['external_reference'] ?? null,
            ]);

            $order->syncTotalPaid();

            $activity = activity()
                ->performedOn($order)
                ->withProperties(['payment_id' => $payment->id, 'amount' => $payment->amount, 'important' => true]);
            if ($user) {
                $activity->causedBy($user);
            }
            $activity->log('payment_added');

            return $payment;
        });

        OrderService::bumpAggregatesVersion($order->tenant_id);

        return $payment;
    }

    /**
     * Delete a payment and resynchronize order totals under an order row
     * lock, mirroring addPayment's locking so a concurrent payment cannot
     * interleave with the delete + total_paid recalculation.
     */
    public function deletePayment(Order $order, Payment $payment): void
    {
        DB::transaction(function () use ($order, $payment): void {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            $payment->delete();
            $order->syncTotalPaid();
        });

        OrderService::bumpAggregatesVersion($order->tenant_id);
    }

    /**
     * Convert a monetary value (float, numeric string, or decimal-cast
     * model attribute) into integer cents. PHP round() is half-away-from-
     * zero, i.e. half-up for the non-negative money values used here, so
     * binary-float noise below half a cent can never drift a persisted cent.
     */
    private function toCents(float|string|int|null $value): int
    {
        return (int) round((float) $value * 100);
    }

    /**
     * Render integer cents back to a decimal amount for the decimal:2
     * columns; cents are exact so no further rounding occurs.
     */
    private function toAmount(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
