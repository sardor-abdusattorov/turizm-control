<?php

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function adminPanel()
{
    return Filament::getPanel('admin');
}

it('keeps a user with no permissions out of the panel', function () {
    $user = User::factory()->create();

    expect($user->canAccessPanel(adminPanel()))->toBeFalse();
});

it('keeps a user whose role grants no permissions out of the panel', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('manager', 'web'));

    expect($user->fresh()->canAccessPanel(adminPanel()))->toBeFalse();
});

it('admits a user who holds at least one permission', function () {
    $user = userWithPermission('view_any_contract');

    expect($user->canAccessPanel(adminPanel()))->toBeTrue();
});

it('always admits a super admin even before permissions are synced', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('super_admin', 'web'));

    expect($user->fresh()->canAccessPanel(adminPanel()))->toBeTrue();
});
