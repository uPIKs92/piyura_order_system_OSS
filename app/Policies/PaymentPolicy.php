<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isOwner() ? true : null;
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
        return $payment->order->user_id === $user->id;
    }
}
