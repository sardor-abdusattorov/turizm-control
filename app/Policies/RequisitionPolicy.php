<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Requisition;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class RequisitionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_requisition');
    }

    /**
     * Beyond the permission, a requisition is only visible to the two people
     * it concerns — its author and its reviewer — unless the user holds
     * oversight over the whole registry.
     */
    public function view(AuthUser $authUser, Requisition $requisition): bool
    {
        if (! $authUser->can('view_requisition')) {
            return false;
        }

        return $authUser->can('view_all_requisitions')
            || $requisition->author_id === $authUser->getKey()
            || $requisition->approvals()->where('user_id', $authUser->getKey())->exists();
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_requisition');
    }

    public function update(AuthUser $authUser, Requisition $requisition): bool
    {
        return $authUser->can('update_requisition')
            && $requisition->canBeEditedBy($authUser);
    }

    public function delete(AuthUser $authUser, Requisition $requisition): bool
    {
        return $authUser->can('delete_requisition')
            && $requisition->canBeEditedBy($authUser);
    }

    public function restore(AuthUser $authUser, Requisition $requisition): bool
    {
        return $authUser->can('restore_requisition');
    }

    public function forceDelete(AuthUser $authUser, Requisition $requisition): bool
    {
        return $authUser->can('force_delete_requisition');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_requisition');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_requisition');
    }

    public function replicate(AuthUser $authUser, Requisition $requisition): bool
    {
        return $authUser->can('replicate_requisition');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_requisition');
    }
}
