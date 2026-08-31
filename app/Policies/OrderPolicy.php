<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class OrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_order');
    }

    public function view(AuthUser $authUser, Order $order): bool
    {
        return $authUser->can('view_order');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_order');
    }

    public function update(AuthUser $authUser, Order $order): bool
    {
        return $authUser->can('update_order');
    }

    public function delete(AuthUser $authUser, Order $order): bool
    {
        return $authUser->can('delete_order');
    }

    public function restore(AuthUser $authUser, Order $order): bool
    {
        return $authUser->can('restore_order');
    }

    public function forceDelete(AuthUser $authUser, Order $order): bool
    {
        return $authUser->can('force_delete_order');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_order');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_order');
    }

    public function replicate(AuthUser $authUser, Order $order): bool
    {
        return $authUser->can('replicate_order');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_order');
    }
}
