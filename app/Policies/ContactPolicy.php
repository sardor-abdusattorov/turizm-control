<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Contact;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ContactPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_contact');
    }

    public function view(AuthUser $authUser, Contact $contact): bool
    {
        return $authUser->can('view_contact');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_contact');
    }

    public function update(AuthUser $authUser, Contact $contact): bool
    {
        return $authUser->can('update_contact');
    }

    public function delete(AuthUser $authUser, Contact $contact): bool
    {
        return $authUser->can('delete_contact');
    }

    public function restore(AuthUser $authUser, Contact $contact): bool
    {
        return $authUser->can('restore_contact');
    }

    public function forceDelete(AuthUser $authUser, Contact $contact): bool
    {
        return $authUser->can('force_delete_contact');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_contact');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_contact');
    }

    public function replicate(AuthUser $authUser, Contact $contact): bool
    {
        return $authUser->can('replicate_contact');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_contact');
    }
}
