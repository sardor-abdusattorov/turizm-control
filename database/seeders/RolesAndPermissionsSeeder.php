<?php

namespace Database\Seeders;

use App\Filament\Resources\Contacts\Widgets\ContactBankAccountsTableWidget;
use App\Filament\Resources\Contracts\Widgets\ContractApprovalChainTableWidget;
use App\Filament\Resources\Contracts\Widgets\ContractApproversTableWidget;
use App\Filament\Resources\Contracts\Widgets\ContractPaymentsTableWidget;
use App\Filament\Widgets\ApprovalsTimelineWidget;
use App\Filament\Widgets\Counterparty\CounterpartyContractsTableWidget;
use App\Filament\Widgets\Counterparty\CounterpartyProjectsTableWidget;
use App\Filament\Widgets\Dashboard\ProjectContractsTableWidget;
use App\Filament\Widgets\Dashboard\ProjectParticipantsTableWidget;
use App\Filament\Widgets\DocumentHistoryTimelineWidget;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
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

        foreach ((array) config('filament-shield.custom_permissions') as $customPermission) {
            Permission::findOrCreate($customPermission, 'web');
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::where('guard_name', 'web')->pluck('name'));

        $contractWidgets = $this->widgetPermissions(
            ContractApprovalChainTableWidget::class,
            ContractApproversTableWidget::class,
            ContractPaymentsTableWidget::class,
            DocumentHistoryTimelineWidget::class,
        );
        $counterpartyWidgets = $this->widgetPermissions(
            CounterpartyContractsTableWidget::class,
            CounterpartyProjectsTableWidget::class,
            ContactBankAccountsTableWidget::class,
        );
        $projectWidgets = $this->widgetPermissions(
            ProjectContractsTableWidget::class,
            ProjectParticipantsTableWidget::class,
        );
        $requisitionWidgets = $this->widgetPermissions(ApprovalsTimelineWidget::class);

        $this->syncRole('director', [
            ...$contractWidgets,
            ...$projectWidgets,
            ...$requisitionWidgets,
            'view_all_contracts',
            'approve_contracts',
            'export_contract',
            'export_payment',
            'export_project',
            'export_press_tour',
            'view_profile_settings',
            ...$this->resourcePermissions('contract', ['view_any', 'view']),
            ...$this->resourcePermissions('order', ['view_any', 'view']),
            ...$this->resourcePermissions('payment', ['view_any', 'view']),
            ...$this->resourcePermissions('project', ['view_any', 'view']),
            ...$this->resourcePermissions('press_tour', ['view_any', 'view']),
            ...$this->resourcePermissions('requisition', ['view_any', 'view']),
            'view_all_requisitions',
        ]);

        $this->syncRole('manager', [
            ...$contractWidgets,
            ...$counterpartyWidgets,
            ...$projectWidgets,
            ...$requisitionWidgets,
            'export_contract',
            'export_contact',
            'export_project',
            'export_press_tour',
            'export_sponsor',
            'view_profile_settings',
            ...$this->resourcePermissions('contract', ['view_any', 'view', 'create', 'update']),
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
            ...$this->resourcePermissions('requisition', ['view_any', 'view', 'create', 'update', 'delete']),
        ]);

        $this->syncRole('legal_officer', [
            ...$contractWidgets,
            ...$counterpartyWidgets,
            'view_all_contracts',
            'approve_contracts',
            'export_contract',
            'export_contact',
            'view_profile_settings',
            ...$this->resourcePermissions('contract', ['view_any', 'view']),
            ...$this->resourcePermissions('contact', ['view_any', 'view']),
        ]);

        $this->syncRole('accountant', [
            ...$contractWidgets,
            ...$counterpartyWidgets,
            ...$projectWidgets,
            ...$requisitionWidgets,
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
            ...$this->resourcePermissions('project', ['view_any', 'view']),
            ...$this->resourcePermissions('requisition', ['view_any', 'view']),
        ]);
    }

    /** @param  list<string>  $permissions */
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
     * @param  list<string>  $abilities
     * @return list<string>
     */
    protected function resourcePermissions(string $resource, array $abilities): array
    {
        return array_map(fn (string $ability): string => "{$ability}_{$resource}", $abilities);
    }

    /**
     * @param  class-string  ...$widgets
     * @return list<string>
     */
    protected function widgetPermissions(string ...$widgets): array
    {
        $registered = FilamentShield::getWidgets() ?? [];

        return array_values(array_filter(array_map(
            fn (string $widget): ?string => array_key_first($registered[$widget]['permissions'] ?? []),
            $widgets,
        )));
    }
}
