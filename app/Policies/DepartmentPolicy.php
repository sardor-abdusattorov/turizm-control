<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Department;
use Illuminate\Auth\Access\HandlesAuthorization;

class DepartmentPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_department');
    }

    public function view(AuthUser $authUser, Department $department): bool
    {
        return $authUser->can('view_department');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_department');
    }

    public function update(AuthUser $authUser, Department $department): bool
    {
        return $authUser->can('update_department');
    }

    public function delete(AuthUser $authUser, Department $department): bool
    {
        return $authUser->can('delete_department');
    }

    public function restore(AuthUser $authUser, Department $department): bool
    {
        return $authUser->can('restore_department');
    }

    public function forceDelete(AuthUser $authUser, Department $department): bool
    {
        return $authUser->can('force_delete_department');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_department');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_department');
    }

    public function replicate(AuthUser $authUser, Department $department): bool
    {
        return $authUser->can('replicate_department');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_department');
    }

}