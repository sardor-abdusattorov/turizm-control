<?php

use App\Filament\Resources\Contacts\Pages\ViewContact;
use App\Filament\Resources\Contracts\Widgets\ContractPaymentsTableWidget;
use App\Filament\Widgets\ApprovalsTimelineWidget;
use App\Filament\Widgets\Counterparty\CounterpartyContractsTableWidget;
use App\Filament\Widgets\Dashboard\ProjectContractsTableWidget;
use App\Filament\Widgets\DocumentHistoryTimelineWidget;
use App\Models\Contact;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function widgetPermission(string $widget): string
{
    return array_key_first(FilamentShield::getWidgets()[$widget]['permissions']);
}

it('exposes every embedded widget to Shield', function () {
    expect(FilamentShield::getWidgets())
        ->toHaveKeys([
            ContractPaymentsTableWidget::class,
            DocumentHistoryTimelineWidget::class,
            ApprovalsTimelineWidget::class,
            ProjectContractsTableWidget::class,
            CounterpartyContractsTableWidget::class,
        ]);
});

it('hides a widget until its permission is granted', function (string $widget) {
    $user = userWithPermission('view_any_contract', 'view_any_project');
    actingAs($user);

    expect($widget::canView())->toBeFalse();

    Permission::findOrCreate(widgetPermission($widget), 'web');
    $user->givePermissionTo(widgetPermission($widget));
    actingAs($user->fresh());

    expect($widget::canView())->toBeTrue();
})->with([
    ContractPaymentsTableWidget::class,
    ProjectContractsTableWidget::class,
    ApprovalsTimelineWidget::class,
]);

it('drops the counterparty tab from the contact page for a user without the widget permission', function () {
    $contact = Contact::factory()->create();

    actingAs(userWithPermission('view_any_contact', 'view_contact'));

    Livewire::test(ViewContact::class, ['record' => $contact->id])
        ->assertDontSeeLivewire(CounterpartyContractsTableWidget::class);

    actingAs(userWithPermission('view_any_contact', 'view_contact', widgetPermission(CounterpartyContractsTableWidget::class)));

    Livewire::test(ViewContact::class, ['record' => $contact->id])
        ->assertSeeLivewire(CounterpartyContractsTableWidget::class);
});

it('hands the seeded roles the widgets that match their registries', function () {
    foreach (FilamentShield::getWidgets() as $widget) {
        Permission::findOrCreate(array_key_first($widget['permissions']), 'web');
    }

    $this->seed(RolesAndPermissionsSeeder::class);

    $manager = Role::findByName('manager')->permissions->pluck('name');
    $legal = Role::findByName('legal_officer')->permissions->pluck('name');

    expect($manager)
        ->toContain(widgetPermission(ContractPaymentsTableWidget::class))
        ->toContain(widgetPermission(ProjectContractsTableWidget::class))
        ->toContain(widgetPermission(CounterpartyContractsTableWidget::class))
        ->and($legal)
        ->toContain(widgetPermission(ContractPaymentsTableWidget::class))
        ->not->toContain(widgetPermission(ProjectContractsTableWidget::class));
});
