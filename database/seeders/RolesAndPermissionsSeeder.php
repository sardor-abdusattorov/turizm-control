<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Single source of truth for application roles + their permission sets.
 * Run after Shield has generated the per-resource permissions
 * (view_any_x, update_x, ...) via `php artisan shield:generate`; the
 * project:update command already wires that for you. Re-running this
 * seeder is safe: it only assigns existing permissions and never
 * destroys data.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Make sure Shield-derived permissions are present before we wire roles to them.
        // --all picks up every resource/page/widget + the custom_permissions list.
        Artisan::call('shield:generate', [
            '--all' => true,
            '--ignore-existing-policies' => true,
        ]);

        // Reset Spatie's in-memory permission cache so the role -> permission
        // sync below sees everything Shield just created.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1) super_admin — sees and does everything.
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::where('guard_name', 'web')->pluck('name'));

        // 2) director — final-sign-off role. Sees every contract, exports them,
        //    runs settings. No need to manage users/roles.
        $this->syncRole('director', [
            // Settings + admin oversight
            'manage_settings',
            'view_all_contracts',
            'export_contract',
            // Contracts + adjacent reference data
            ...$this->resourcePermissions('contract', ['view_any', 'view', 'update']),
            ...$this->resourcePermissions('contract_template', ['view_any', 'view']),
            ...$this->resourcePermissions('order', ['view_any', 'view']),
            ...$this->resourcePermissions('contact', ['view_any', 'view']),
            ...$this->resourcePermissions('currency', ['view_any', 'view']),
            ...$this->resourcePermissions('department', ['view_any', 'view']),
            ...$this->resourcePermissions('position', ['view_any', 'view']),
            ...$this->resourcePermissions('order_type', ['view_any', 'view']),
            ...$this->resourcePermissions('activity', ['view_any', 'view']),
        ]);

        // 3) manager — creates and edits their own contracts, no oversight,
        //    no export. They see only their own (the visibleTo scope handles that).
        $this->syncRole('manager', [
            ...$this->resourcePermissions('contract', ['view_any', 'view', 'create', 'update']),
            ...$this->resourcePermissions('contract_template', ['view_any', 'view']),
            ...$this->resourcePermissions('order', ['view_any', 'view', 'create', 'update']),
            ...$this->resourcePermissions('contact', ['view_any', 'view', 'create', 'update']),
            ...$this->resourcePermissions('currency', ['view_any']),
            ...$this->resourcePermissions('department', ['view_any']),
            ...$this->resourcePermissions('position', ['view_any']),
            ...$this->resourcePermissions('order_type', ['view_any']),
        ]);

        // 4) legal_officer — approves contracts. They see only contracts where
        //    they appear in the approval chain (or are responsible), no export.
        $this->syncRole('legal_officer', [
            ...$this->resourcePermissions('contract', ['view_any', 'view']),
            ...$this->resourcePermissions('contract_template', ['view_any', 'view']),
            ...$this->resourcePermissions('contact', ['view_any', 'view']),
        ]);

        // 5) accountant — same shape as legal_officer (approver scope only).
        $this->syncRole('accountant', [
            ...$this->resourcePermissions('contract', ['view_any', 'view']),
            ...$this->resourcePermissions('contract_template', ['view_any', 'view']),
            ...$this->resourcePermissions('contact', ['view_any', 'view']),
        ]);
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
