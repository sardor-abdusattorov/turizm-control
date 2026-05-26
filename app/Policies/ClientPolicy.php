<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Client;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_client');
    }

    public function view(AuthUser $authUser, Client $client): bool
    {
        return $authUser->can('view_client');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_client');
    }

    public function update(AuthUser $authUser, Client $client): bool
    {
        return $authUser->can('update_client');
    }

    public function delete(AuthUser $authUser, Client $client): bool
    {
        return $authUser->can('delete_client');
    }

    public function restore(AuthUser $authUser, Client $client): bool
    {
        return $authUser->can('restore_client');
    }

    public function forceDelete(AuthUser $authUser, Client $client): bool
    {
        return $authUser->can('force_delete_client');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_client');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_client');
    }

    public function replicate(AuthUser $authUser, Client $client): bool
    {
        return $authUser->can('replicate_client');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_client');
    }

}