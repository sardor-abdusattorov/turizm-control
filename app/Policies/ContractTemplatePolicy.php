<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ContractTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContractTemplatePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_contract_template');
    }

    public function view(AuthUser $authUser, ContractTemplate $contractTemplate): bool
    {
        return $authUser->can('view_contract_template');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_contract_template');
    }

    public function update(AuthUser $authUser, ContractTemplate $contractTemplate): bool
    {
        return $authUser->can('update_contract_template');
    }

    public function delete(AuthUser $authUser, ContractTemplate $contractTemplate): bool
    {
        return $authUser->can('delete_contract_template');
    }

    public function restore(AuthUser $authUser, ContractTemplate $contractTemplate): bool
    {
        return $authUser->can('restore_contract_template');
    }

    public function forceDelete(AuthUser $authUser, ContractTemplate $contractTemplate): bool
    {
        return $authUser->can('force_delete_contract_template');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_contract_template');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_contract_template');
    }

    public function replicate(AuthUser $authUser, ContractTemplate $contractTemplate): bool
    {
        return $authUser->can('replicate_contract_template');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_contract_template');
    }

}