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
        return $authUser->can('delete_contract');
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