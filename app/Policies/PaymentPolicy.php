<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isOwner() && $ability !== 'delete') {
            return true;
        }

        return null;
    }

    public function viewAny(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }

    public function create(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->isOwner() && $user->tenant_id === $payment->order->tenant_id;
    }
}
