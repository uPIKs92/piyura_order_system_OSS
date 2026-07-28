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
    public function addPayment(Order $order, User $user, array $data): Payment
    {
        return DB::transaction(function () use ($order, $user, $data) {
            $payment = Payment::create([
                'order_id' => $order->id,
                'amount' => $data['amount'],
                'metode' => PaymentMethod::from($data['metode']),
                'paid_at' => $data['paid_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $order->syncTotalPaid();

            activity()
                ->performedOn($order)
                ->causedBy($user)
                ->withProperties(['payment_id' => $payment->id, 'amount' => $payment->amount, 'important' => true])
                ->log('payment_added');

            return $payment;
        });
    }
}
