<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Payment;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_payment');
    }

    public function view(AuthUser $authUser, Payment $payment): bool
    {
        return $authUser->can('view_payment');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_payment');
    }

    public function update(AuthUser $authUser, Payment $payment): bool
    {
        return $authUser->can('update_payment');
    }

    public function delete(AuthUser $authUser, Payment $payment): bool
    {
        return $authUser->can('delete_payment');
    }

    public function restore(AuthUser $authUser, Payment $payment): bool
    {
        return $authUser->can('restore_payment');
    }

    public function forceDelete(AuthUser $authUser, Payment $payment): bool
    {
        return $authUser->can('force_delete_payment');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_payment');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_payment');
    }

    public function replicate(AuthUser $authUser, Payment $payment): bool
    {
        return $authUser->can('replicate_payment');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_payment');
    }

}