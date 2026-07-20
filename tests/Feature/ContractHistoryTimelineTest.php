<?php

use App\Filament\Resources\Contracts\Pages\ViewContract;
use App\Filament\Resources\Contracts\Widgets\ContractHistoryTimelineWidget;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('renders the contract history as a timeline with localized workflow labels', function () {
    $user = User::factory()->create();
    actingAs($user);

    $contract = Contract::factory()->create();

    activity()
        ->performedOn($contract)
        ->causedBy($user)
        ->event('Contract Submitted')
        ->log('Contract Submitted');

    Livewire::test(ContractHistoryTimelineWidget::class, ['contractId' => $contract->id])
        ->assertOk()
        // The stored raw English event renders through the app.activity.*
        // translation, not verbatim.
        ->assertSee(__('app.activity.submitted'));
});

it('opens the approver details as a native Filament modal', function () {
    $manager = User::factory()->create();
    $approver = User::factory()->create(['name' => 'Alisher Yuldoshev', 'status' => User::STATUS_ACTIVE]);

    Permission::findOrCreate('view_any_contract', 'web');
    Permission::findOrCreate('view_contract', 'web');
    $manager->givePermissionTo(['view_any_contract', 'view_contract']);
    actingAs($manager->fresh());

    $contract = Contract::factory()->create([
        'responsible_id' => $manager->id,
        'status' => Contract::STATUS_IN_REVIEW,
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $approver->id, 'order' => 1,
        'status' => ContractApprover::STATUS_PENDING,
    ]);

    // The action mounts as a native Filament modal (heading closure, args)…
    Livewire::test(ViewContract::class, ['record' => $contract->id])
        ->mountAction('approverDetails', arguments: ['user' => $approver->id])
        ->assertActionMounted('approverDetails');

    // …and its content view carries the step tile and the records table.
    // (The modal body itself lives in a lazily-rendered wire:partial the
    // test snapshot does not include, so the view renders directly.)
    $page = app(ViewContract::class);
    $page->record = $contract->fresh()->loadMissing(['approvers.user.department', 'approvers.user.position', 'activeApprovers']);

    $html = view('filament.resources.contracts.pages.view-contract.approver-details', [
        'record' => $page->record,
        'page' => $page,
        'userId' => $approver->id,
    ])->render();

    expect($html)->toContain(__('app.label.step'))
        ->toContain(__('app.label.system_note'))
        ->toContain('cw-rt');
});

it('filters history down to workflow events only', function () {
    $user = User::factory()->create();
    actingAs($user);

    $contract = Contract::factory()->create();

    activity()->performedOn($contract)->event('Contract Submitted')->log('Contract Submitted');
    activity()->performedOn($contract)->event('updated')->log('updated');

    Livewire::test(ContractHistoryTimelineWidget::class, ['contractId' => $contract->id])
        ->filterTable('group', 'workflow')
        ->assertSee(__('app.activity.submitted'))
        ->assertDontSee(__('app.activity.updated'));
});
