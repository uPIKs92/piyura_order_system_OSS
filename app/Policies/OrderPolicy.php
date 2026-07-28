<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isOwner() && $ability !== 'forceDelete') {
            return null;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return $user->tenant_id === $order->tenant_id
            && ($user->isOwner() || $order->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Order $order): bool
    {
        return $user->tenant_id === $order->tenant_id
            && ($user->isOwner() || $order->user_id === $user->id);
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->isOwner() && $user->tenant_id === $order->tenant_id;
    }

    public function forceDelete(User $user, Order $order): bool
    {
        return $user->isOwner();
    }
}
