<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Sponsor;
use Illuminate\Auth\Access\HandlesAuthorization;

class SponsorPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_sponsor');
    }

    public function view(AuthUser $authUser, Sponsor $sponsor): bool
    {
        return $authUser->can('view_sponsor');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_sponsor');
    }

    public function update(AuthUser $authUser, Sponsor $sponsor): bool
    {
        return $authUser->can('update_sponsor');
    }

    public function delete(AuthUser $authUser, Sponsor $sponsor): bool
    {
        return $authUser->can('delete_sponsor');
    }

    public function restore(AuthUser $authUser, Sponsor $sponsor): bool
    {
        return $authUser->can('restore_sponsor');
    }

    public function forceDelete(AuthUser $authUser, Sponsor $sponsor): bool
    {
        return $authUser->can('force_delete_sponsor');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_sponsor');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_sponsor');
    }

    public function replicate(AuthUser $authUser, Sponsor $sponsor): bool
    {
        return $authUser->can('replicate_sponsor');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_sponsor');
    }

}