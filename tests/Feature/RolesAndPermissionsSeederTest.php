<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // syncRole only assigns permissions that already exist, so create the ones
    // these assertions touch (Shield would generate them via shield:generate).
    foreach ([
        'approve_contracts',
        'view_all_contracts',
        'view_profile_settings',
        'view_any_contract',
        'view_contract',
        'create_contract',
        'update_contract',
        'delete_contract',
    ] as $name) {
        Permission::findOrCreate($name, 'web');
    }

    (new RolesAndPermissionsSeeder)->run();
});

it('gives every working role access to their own profile', function () {
    foreach (['director', 'manager', 'legal_officer', 'accountant'] as $name) {
        expect(Role::findByName($name, 'web')->hasPermissionTo('view_profile_settings'))->toBeTrue();
    }
});

it('lets legal and accounting approve contracts but not author them', function () {
    foreach (['legal_officer', 'accountant'] as $name) {
        $role = Role::findByName($name, 'web');

        expect($role->hasPermissionTo('approve_contracts'))->toBeTrue()
            ->and($role->hasPermissionTo('view_any_contract'))->toBeTrue()
            ->and($role->hasPermissionTo('create_contract'))->toBeFalse()
            ->and($role->hasPermissionTo('update_contract'))->toBeFalse();
    }
});

it('lets the manager build contracts but never approve them', function () {
    $manager = Role::findByName('manager', 'web');

    expect($manager->hasPermissionTo('create_contract'))->toBeTrue()
        ->and($manager->hasPermissionTo('update_contract'))->toBeTrue()
        ->and($manager->hasPermissionTo('approve_contracts'))->toBeFalse();
});

it('gives the super admin every permission', function () {
    $admin = Role::findByName('super_admin', 'web');

    expect($admin->hasPermissionTo('approve_contracts'))->toBeTrue()
        ->and($admin->hasPermissionTo('view_any_contract'))->toBeTrue()
        ->and($admin->hasPermissionTo('delete_contract'))->toBeTrue();
});
