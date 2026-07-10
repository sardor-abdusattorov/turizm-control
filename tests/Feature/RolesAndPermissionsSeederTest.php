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
        'view_any_contract_template',
        'view_contract_template',
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

it('lets legal and accounting approve contracts but not browse templates', function () {
    foreach (['legal_officer', 'accountant'] as $name) {
        $role = Role::findByName($name, 'web');

        expect($role->hasPermissionTo('approve_contracts'))->toBeTrue()
            ->and($role->hasPermissionTo('view_any_contract'))->toBeTrue()
            // They review contracts, they do not author them.
            ->and($role->hasPermissionTo('view_any_contract_template'))->toBeFalse()
            ->and($role->hasPermissionTo('view_contract_template'))->toBeFalse();
    }
});

it('keeps contract-template access with the manager who builds contracts', function () {
    $manager = Role::findByName('manager', 'web');

    expect($manager->hasPermissionTo('view_any_contract_template'))->toBeTrue()
        // The manager submits but never approves.
        ->and($manager->hasPermissionTo('approve_contracts'))->toBeFalse();
});

it('gives the super admin every permission, templates included', function () {
    $admin = Role::findByName('super_admin', 'web');

    expect($admin->hasPermissionTo('approve_contracts'))->toBeTrue()
        ->and($admin->hasPermissionTo('view_any_contract_template'))->toBeTrue();
});
