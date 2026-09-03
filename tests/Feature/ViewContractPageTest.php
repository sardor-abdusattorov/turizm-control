<?php

use App\Filament\Resources\Contracts\Pages\ViewContract;
use App\Filament\Resources\Contracts\Widgets\ContractApprovalChainTableWidget;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function viewerWithAccess(): User
{
    $user = User::factory()->create();

    foreach ([
        'view_any_contract',
        'view_contract',
        'update_contract',
        'view_contract_approval_chain_table_widget',
        'view_contract_approvers_table_widget',
        'view_contract_payments_table_widget',
        'view_document_history_timeline_widget',
    ] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }

    return $user;
}

it('shows localized human labels in the history, not raw English event strings', function () {
    $user = viewerWithAccess();
    $contract = Contract::factory()->create([
        'responsible_id' => $user->id,
        'status' => Contract::STATUS_PENDING_DIRECTOR,
    ]);

    activity()
        ->performedOn($contract)
        ->event('Contract Awaiting Director')
        ->log('Contract Awaiting Director — '.$contract->number);

    actingAs($user);

    $html = Livewire::test(ViewContract::class, ['record' => $contract->id])->html();

    expect($html)->toContain(__('app.activity.awaiting_director'))
        ->and($html)->not->toContain('Contract Awaiting Director');
});

it('renders the custom contract view page for a draft contract', function () {
    $user = viewerWithAccess();
    $contract = Contract::factory()->create([
        'responsible_id' => $user->id,
        'status' => Contract::STATUS_DRAFT,
    ]);

    actingAs($user);

    Livewire::test(ViewContract::class, ['record' => $contract->id])->assertOk();
});

it('renders the view page with an approval chain and history', function () {
    $user = viewerWithAccess();
    $approvers = User::factory()->count(2)->create();

    $contract = Contract::factory()->create([
        'responsible_id' => $user->id,
        'status' => Contract::STATUS_IN_REVIEW,
    ]);

    foreach ($approvers as $index => $approver) {
        ContractApprover::factory()->create([
            'contract_id' => $contract->id,
            'user_id' => $approver->id,
            'order' => $index + 1,
            'due_at' => now()->addDays(2),
        ]);
    }

    actingAs($user);

    Livewire::test(ViewContract::class, ['record' => $contract->id])
        ->assertOk()
        ->assertSee($approvers->first()->name);
});

it('keeps a cancelled approval verdict and comment in the per-approver modal', function () {
    $user = viewerWithAccess();
    $approver = User::factory()->create(['name' => 'Dilshod Approver']);

    $contract = Contract::factory()->create([
        'responsible_id' => $user->id,
        'status' => Contract::STATUS_IN_REVIEW,
        'number' => 'C-VIEW-CANCELLED',
    ]);

    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $approver->id, 'order' => 1,
        'status' => ContractApprover::STATUS_APPROVED, 'acted_at' => now()->subHour(),
        'comment' => 'Looks good, ship it.',
    ]);

    $contract->update(['title' => 'Edited mid-flow']);

    actingAs($user);

    $page = Livewire::test(ViewContract::class, ['record' => $contract->id]);

    expect($page->html())->not->toContain('cw-history-btn');

    $modal = view('filament.resources.contracts.widgets.approver-details', [
        'record' => $contract->fresh()->loadMissing(['approvers.user', 'activeApprovers']),
        'userId' => $approver->id,
        'activities' => collect(),
    ])->render();

    expect($modal)->toContain('Looks good, ship it.')
        ->and($modal)->toContain('is-past')
        ->and($modal)->toContain(__('app.message.invalidated_on_edit'));
});

it('marks the director step as the final sign-off in the chain', function () {
    Role::firstOrCreate(['name' => Contract::DIRECTOR_ROLE, 'guard_name' => 'web']);
    $director = User::factory()->create(['status' => User::STATUS_ACTIVE]);
    $director->assignRole(Contract::DIRECTOR_ROLE);

    $user = viewerWithAccess();
    $contract = Contract::factory()->create([
        'responsible_id' => $user->id,
        'status' => Contract::STATUS_IN_REVIEW,
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => User::factory()->create()->id, 'order' => 1,
        'status' => ContractApprover::STATUS_APPROVED,
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $director->id, 'order' => 2,
        'status' => ContractApprover::STATUS_PENDING,
    ]);

    actingAs($user);

    Livewire::test(ContractApprovalChainTableWidget::class, ['contractId' => $contract->id])
        ->assertOk()
        ->assertSee(__('app.label.final_sign_off'));
});

it('never offers a PDF preview — there is no converter any more', function () {
    Storage::fake('local');

    $user = viewerWithAccess();
    $contract = Contract::factory()->create([
        'responsible_id' => $user->id,
        'status' => Contract::STATUS_IN_REVIEW,
    ]);
    actingAs($user);

    $html = Livewire::test(ViewContract::class, ['record' => $contract->id])->html();

    expect($html)->not->toContain($contract->id.'/pdf')
        ->and($html)->not->toContain('pdf/inline')
        ->and($html)->not->toContain('pdf.download');
});

it('renders the approval chain as a Filament table with distinct step statuses', function () {
    $user = viewerWithAccess();
    $first = User::factory()->create();
    $second = User::factory()->create();

    $contract = Contract::factory()->create([
        'responsible_id' => $user->id,
        'status' => Contract::STATUS_IN_REVIEW,
    ]);

    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $first->id, 'order' => 1,
        'status' => ContractApprover::STATUS_PENDING, 'due_at' => now()->addDays(2),
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $second->id, 'order' => 2,
        'status' => ContractApprover::STATUS_QUEUED,
    ]);

    actingAs($user);

    $html = Livewire::test(ViewContract::class, ['record' => $contract->id])->html();

    expect($html)->toContain("tab: 'overview'")
        ->and($html)->toContain('fi-tabs')
        ->and($html)->toContain('rec-tabs-row')

        ->and($html)->toContain('rec-tabs-row__side')
        ->and($html)->not->toContain('cw-meta')

        ->and($html)->toContain('cw-prog__track')
        ->and($html)->toContain('cw-prog__fill')
        ->and($html)->toContain('cw-prog__legend')
        ->and($html)->toContain('cw-prog__await')

        ->and($html)->not->toContain('cw-chain')
        ->and($html)->not->toContain('cw-eye');

    Livewire::test(ContractApprovalChainTableWidget::class, ['contractId' => $contract->id])
        ->assertOk()
        ->assertSee($first->name)
        ->assertSee($second->name)

        ->assertSee(__('app.contract_approver.status.queued'))
        ->assertSee(__('app.contract_approver.status.pending'));
});
