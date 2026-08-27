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

    foreach (['view_any_contract', 'view_contract', 'update_contract'] as $ability) {
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

    // The accountant's approval that parked the contract for the director is
    // stored as the raw event "Contract Awaiting Director".
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

    // Editing a trigger field cancels the chain; the verdict must survive.
    $contract->update(['title' => 'Edited mid-flow']);

    actingAs($user);

    $page = Livewire::test(ViewContract::class, ['record' => $contract->id]);

    // The standalone history button/modal is gone — details live behind the
    // chain table's row action.
    expect($page->html())->not->toContain('cw-history-btn');

    // The per-approver modal keeps the cancelled verdict, the comment and
    // the system note explaining WHY the row was cancelled.
    $modal = view('filament.resources.contracts.widgets.approver-details', [
        'record' => $contract->fresh()->loadMissing(['approvers.user', 'activeApprovers']),
        'userId' => $approver->id,
        'activities' => collect(),
    ])->render();

    expect($modal)->toContain('Looks good, ship it.')  // their own comment
        ->and($modal)->toContain('is-past')            // cancelled row, dimmed
        ->and($modal)->toContain(__('app.message.invalidated_on_edit'));
});

it('offers the document as a download on the card, with no embedded viewer', function () {
    Storage::fake('local');

    $user = viewerWithAccess();
    $contract = Contract::factory()->create([
        'responsible_id' => $user->id,
        'status' => Contract::STATUS_APPROVED,
    ]);
    Storage::disk('local')->put($contract->documentPath(), 'fake-docx');

    actingAs($user);

    $html = Livewire::test(ViewContract::class, ['record' => $contract->id])->html();

    // With the online editor gone the card's one action hands the .docx over;
    // nothing is rendered in-page.
    expect($html)->toContain(route('contracts.document.download', ['contract' => $contract]))
        ->and($html)->not->toContain('<iframe');
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
    Storage::disk('local')->put($contract->documentPath(), 'fake-docx');

    actingAs($user);

    $html = Livewire::test(ViewContract::class, ['record' => $contract->id])->html();

    expect($html)->not->toContain('/pdf');
});

it('renders the approval chain as a Filament table with distinct step statuses', function () {
    $user = viewerWithAccess();
    $first = User::factory()->create();
    $second = User::factory()->create();

    $contract = Contract::factory()->create([
        'responsible_id' => $user->id,
        'status' => Contract::STATUS_IN_REVIEW,
    ]);

    // First reviewing now, second still queued behind them.
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

    // Native Filament tabs + Alpine state.
    expect($html)->toContain("tab: 'overview'")
        ->and($html)->toContain('fi-tabs')
        ->and($html)->toContain('rec-tabs-row')
        // Status pill now rides on the tab bar instead of a separate strip.
        ->and($html)->toContain('rec-tabs-row__side')
        ->and($html)->not->toContain('cw-meta')
        // Progress band: a single continuous fill track + status legend + the
        // "Awaiting" tile (the old per-step segmented bar was replaced).
        ->and($html)->toContain('cw-prog__track')
        ->and($html)->toContain('cw-prog__fill')
        ->and($html)->toContain('cw-prog__legend')
        ->and($html)->toContain('cw-prog__await')
        // The chain itself is a nested Filament table widget — the hand-rolled
        // timeline (and its eye button) is gone.
        ->and($html)->not->toContain('cw-chain')
        ->and($html)->not->toContain('cw-eye');

    // The chain widget renders both people and tells the two states apart.
    Livewire::test(ContractApprovalChainTableWidget::class, ['contractId' => $contract->id])
        ->assertOk()
        ->assertSee($first->name)
        ->assertSee($second->name)
        // Queued step shows the "In queue" badge, current shows "Reviewing".
        ->assertSee(__('app.contract_approver.status.queued'))
        ->assertSee(__('app.contract_approver.status.pending'));
});
