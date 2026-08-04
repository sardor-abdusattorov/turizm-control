<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Custom (non-resource) permissions come from the Shield config —
        // ensure they exist even when shield:generate hasn't been re-run, so
        // the role editor can offer them and super_admin picks them up below.
        foreach ((array) config('filament-shield.custom_permissions') as $customPermission) {
            Permission::findOrCreate($customPermission, 'web');
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::where('guard_name', 'web')->pluck('name'));

        // The director only consumes the work that reaches them: every
        // contract (to review + sign off), plus orders and payments. No
        // settings, reference data or template management.
        $this->syncRole('director', [
            'view_all_contracts',
            'approve_contracts',
            'export_contract',
            'export_payment',
            'export_project',
            'view_profile_settings',
            ...$this->resourcePermissions('contract', ['view_any', 'view']),
            ...$this->resourcePermissions('order', ['view_any', 'view']),
            ...$this->resourcePermissions('payment', ['view_any', 'view']),
            ...$this->resourcePermissions('project', ['view_any', 'view']),
            ...$this->resourcePermissions('press_tour', ['view_any', 'view']),
        ]);

        $this->syncRole('manager', [
            'export_contract',
            'export_contact',
            'export_project',
            'export_sponsor',
            'view_profile_settings',
            ...$this->resourcePermissions('contract', ['view_any', 'view', 'create', 'update']),
            ...$this->resourcePermissions('contract_template', ['view_any', 'view']),
            ...$this->resourcePermissions('contract_type', ['view_any']),
            ...$this->resourcePermissions('order', ['view_any', 'view', 'create', 'update']),
            ...$this->resourcePermissions('contact', ['view_any', 'view', 'create', 'update']),
            ...$this->resourcePermissions('currency', ['view_any']),
            ...$this->resourcePermissions('department', ['view_any']),
            ...$this->resourcePermissions('position', ['view_any']),
            ...$this->resourcePermissions('payment', ['view_any', 'view']),
            ...$this->resourcePermissions('project', ['view_any', 'view', 'create', 'update']),
            ...$this->resourcePermissions('press_tour', ['view_any', 'view', 'create', 'update']),
            ...$this->resourcePermissions('sponsor', ['view_any', 'view', 'create', 'update']),
        ]);

        // Legal + accounting review and approve contracts; they do not author
        // them, so contract-template access is intentionally left out (it stays
        // with the manager who builds contracts and the super admin).
        $this->syncRole('legal_officer', [
            'view_all_contracts',
            'approve_contracts',
            'export_contract',
            'export_contact',
            'view_profile_settings',
            ...$this->resourcePermissions('contract', ['view_any', 'view']),
            ...$this->resourcePermissions('contact', ['view_any', 'view']),
        ]);

        $this->syncRole('accountant', [
            'view_all_contracts',
            'approve_contracts',
            'export_contract',
            'export_contact',
            'export_payment',
            'export_project',
            'view_profile_settings',
            ...$this->resourcePermissions('contract', ['view_any', 'view']),
            ...$this->resourcePermissions('contact', ['view_any', 'view']),
            ...$this->resourcePermissions('payment', ['view_any', 'view', 'create']),
            ...$this->resourcePermissions('project', ['view_any', 'view']),        ]);
    }

    /**
     * Assign $permissions to a role, only those that actually exist in the DB
     * — keeps the seeder green even before Shield has been regenerated.
     *
     * @param  list<string>  $permissions
     */
    protected function syncRole(string $name, array $permissions): void
    {
        $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

        $existing = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $permissions)
            ->pluck('name')
            ->all();

        $role->syncPermissions($existing);
    }

    /**
     * Build Shield-style permission names for a resource, e.g.
     *   resourcePermissions('contract', ['view_any', 'update'])
     *     => ['view_any_contract', 'update_contract']
     *
     * @param  list<string>  $abilities
     * @return list<string>
     */
    protected function resourcePermissions(string $resource, array $abilities): array
    {
        return array_map(fn (string $ability): string => "{$ability}_{$resource}", $abilities);
    }
}
