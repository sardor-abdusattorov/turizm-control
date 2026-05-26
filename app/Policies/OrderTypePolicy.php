<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\OrderType;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderTypePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_order_type');
    }

    public function view(AuthUser $authUser, OrderType $orderType): bool
    {
        return $authUser->can('view_order_type');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_order_type');
    }

    public function update(AuthUser $authUser, OrderType $orderType): bool
    {
        return $authUser->can('update_order_type');
    }

    public function delete(AuthUser $authUser, OrderType $orderType): bool
    {
        return $authUser->can('delete_order_type');
    }

    public function restore(AuthUser $authUser, OrderType $orderType): bool
    {
        return $authUser->can('restore_order_type');
    }

    public function forceDelete(AuthUser $authUser, OrderType $orderType): bool
    {
        return $authUser->can('force_delete_order_type');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_order_type');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_order_type');
    }

    public function replicate(AuthUser $authUser, OrderType $orderType): bool
    {
        return $authUser->can('replicate_order_type');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_order_type');
    }

}