<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function view(User $user, User $model): bool
    {
        return ($user->isOwner() || $user->id === $model->id)
            && $user->tenant_id === $model->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isOwner() && $user->tenant_id === $model->tenant_id;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isOwner() && $user->tenant_id === $model->tenant_id;
    }

    public function restore(User $user, User $model): bool
    {
        return $user->isOwner() && $user->tenant_id === $model->tenant_id;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->isOwner() && $user->tenant_id === $model->tenant_id;
    }
}
