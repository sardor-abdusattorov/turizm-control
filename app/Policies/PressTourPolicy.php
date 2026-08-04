<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PressTour;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PressTourPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_press_tour');
    }

    public function view(AuthUser $authUser, PressTour $pressTour): bool
    {
        return $authUser->can('view_press_tour');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_press_tour');
    }

    public function update(AuthUser $authUser, PressTour $pressTour): bool
    {
        return $authUser->can('update_press_tour');
    }

    public function delete(AuthUser $authUser, PressTour $pressTour): bool
    {
        return $authUser->can('delete_press_tour');
    }

    public function restore(AuthUser $authUser, PressTour $pressTour): bool
    {
        return $authUser->can('restore_press_tour');
    }

    public function forceDelete(AuthUser $authUser, PressTour $pressTour): bool
    {
        return $authUser->can('force_delete_press_tour');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_press_tour');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_press_tour');
    }

    public function replicate(AuthUser $authUser, PressTour $pressTour): bool
    {
        return $authUser->can('replicate_press_tour');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_press_tour');
    }
}
