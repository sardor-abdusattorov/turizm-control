<?php

use App\Filament\Resources\Contracts\Widgets\ContractApprovalChainTableWidget;
use App\Filament\Widgets\DocumentHistoryTimelineWidget;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
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

    Livewire::test(DocumentHistoryTimelineWidget::class, DocumentHistoryTimelineWidget::paramsFor($contract))
        ->assertOk()

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
    $approverRecord = ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $approver->id, 'order' => 1,
        'status' => ContractApprover::STATUS_PENDING,
    ]);

    Livewire::test(ContractApprovalChainTableWidget::class, ['contractId' => $contract->id])
        ->mountAction(TestAction::make('approverDetails')->table($approverRecord))
        ->assertActionMounted(TestAction::make('approverDetails')->table($approverRecord));

    $html = view('filament.resources.contracts.widgets.approver-details', [
        'record' => $contract->fresh()->loadMissing(['approvers.user', 'activeApprovers']),
        'userId' => $approver->id,
        'activities' => collect(),
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

    Livewire::test(DocumentHistoryTimelineWidget::class, DocumentHistoryTimelineWidget::paramsFor($contract))
        ->filterTable('group', 'workflow')
        ->assertSee(__('app.activity.submitted'))
        ->assertDontSee(__('app.activity.updated'));
});
