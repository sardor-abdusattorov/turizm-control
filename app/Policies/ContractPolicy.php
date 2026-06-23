<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Contract;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContractPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_contract');
    }

    public function view(AuthUser $authUser, Contract $contract): bool
    {
        return $authUser->can('view_contract');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_contract');
    }

    public function update(AuthUser $authUser, Contract $contract): bool
    {
        return $authUser->can('update_contract');
    }

    public function delete(AuthUser $authUser, Contract $contract): bool
    {
        // Both the permission AND the contract's own rule have to allow it —
        // delete_contract on its own isn't enough to drop a non-Draft
        // contract (canBeDeletedBy enforces "Draft only, by the author or
        // super_admin"). Keeps API / bulk paths in line with the UI rule.
        return $authUser->can('delete_contract')
            && $contract->canBeDeletedBy($authUser);
    }

    public function restore(AuthUser $authUser, Contract $contract): bool
    {
        return $authUser->can('restore_contract');
    }

    public function forceDelete(AuthUser $authUser, Contract $contract): bool
    {
        return $authUser->can('force_delete_contract');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_contract');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_contract');
    }

    public function replicate(AuthUser $authUser, Contract $contract): bool
    {
        return $authUser->can('replicate_contract');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_contract');
    }

}