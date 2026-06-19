<?php

use App\Filament\Resources\Contracts\Pages\ViewContract;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

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

    $html = Livewire::test(ViewContract::class, ['record' => $contract->id])->html();

    expect($html)->toContain('Looks good, ship it.')           // their own comment
        ->and($html)->toContain('Earlier attempts')             // the past-attempts caption
        ->and($html)->toContain(__('app.message.invalidated_on_edit'))
        // The standalone history button/modal is gone — history lives in the eye.
        ->and($html)->not->toContain('cw-history-btn');
});

it('shows a preview button on the document card instead of an embedded pdf iframe', function () {
    Storage::fake('local');

    $user = viewerWithAccess();
    $contract = Contract::factory()->create([
        'responsible_id' => $user->id,
        'status' => Contract::STATUS_IN_REVIEW,
    ]);
    Storage::disk('local')->put($contract->documentPath(), 'fake-docx');

    actingAs($user);

    $html = Livewire::test(ViewContract::class, ['record' => $contract->id])->html();

    expect($html)->toContain(route('contracts.pdf.inline', ['contract' => $contract]))
        ->and($html)->not->toContain('<iframe');
});

it('shows a per-approver detail modal trigger and renders queued steps distinctly', function () {
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

    // Eye-modal wiring + Alpine state + tabs.
    expect($html)->toContain('approver: null')
        ->and($html)->toContain("tab: 'overview'")
        ->and($html)->toContain('cw-tabs')
        ->and($html)->toContain('cw-eye')
        // Status pill now rides on the tab bar instead of a separate strip.
        ->and($html)->toContain('cw-tabs__status')
        ->and($html)->not->toContain('cw-meta')
        // Progress band: segmented bar tinted per state + the "Awaiting" tile.
        ->and($html)->toContain('cw-prog__bar')
        ->and($html)->toContain('cw-seg cw-seg--current')
        ->and($html)->toContain('cw-seg cw-seg--queued')
        ->and($html)->toContain('cw-prog__await')
        // Modals are keyed by user_id so one opener covers all of a person's records.
        ->and($html)->toContain('approver = '.$contract->approvers()->where('order', 1)->first()->user_id)
        ->and($html)->toContain('approver === '.$contract->approvers()->where('order', 1)->first()->user_id)
        // Queued step shows the "In queue" pill, current shows "Reviewing".
        ->and($html)->toContain('In queue')
        ->and($html)->toContain('Reviewing')
        // Timeline day grouping container.
        ->and($html)->toContain('cw-day');
});
