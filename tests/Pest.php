<?php

use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

pest()->extend(TestCase::class)

    ->in('Feature');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

function userWithPermission(string ...$permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user->fresh();
}

/**
 * @template TUsers of User|Collection<int, User>
 *
 * @param  TUsers  $users
 * @return TUsers
 */
function asApprover(User|Collection $users): User|Collection
{
    Permission::findOrCreate('approve_contracts', 'web');

    Collection::wrap($users)->each(
        fn (User $user) => $user->givePermissionTo('approve_contracts'),
    );

    return $users;
}
