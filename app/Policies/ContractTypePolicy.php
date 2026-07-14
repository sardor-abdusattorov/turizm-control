<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContractType;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ContractTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_contract_type');
    }

    public function view(AuthUser $authUser, ContractType $contractType): bool
    {
        return $authUser->can('view_contract_type');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_contract_type');
    }

    public function update(AuthUser $authUser, ContractType $contractType): bool
    {
        return $authUser->can('update_contract_type');
    }

    public function delete(AuthUser $authUser, ContractType $contractType): bool
    {
        return $authUser->can('delete_contract_type');
    }

    public function restore(AuthUser $authUser, ContractType $contractType): bool
    {
        return $authUser->can('restore_contract_type');
    }

    public function forceDelete(AuthUser $authUser, ContractType $contractType): bool
    {
        return $authUser->can('force_delete_contract_type');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_contract_type');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_contract_type');
    }

    public function replicate(AuthUser $authUser, ContractType $contractType): bool
    {
        return $authUser->can('replicate_contract_type');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_contract_type');
    }
}
