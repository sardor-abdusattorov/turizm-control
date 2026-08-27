<?php

use App\Filament\Resources\Contracts\Pages\ViewContract;
use App\Filament\Resources\Contracts\Widgets\ContractApprovalChainTableWidget;
use App\Filament\Resources\Contracts\Widgets\ContractPaymentsTableWidget;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function contractViewer(): User
{
    $user = User::factory()->create();

    foreach (['view_any_contract', 'view_contract'] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }

    return $user->fresh();
}

it('lists the payment ledger in the payments table widget', function () {
    Storage::fake('local');

    $creator = User::factory()->create(['name' => 'Kamola Rashidova']);
    $contract = Contract::factory()->create(['status' => Contract::STATUS_APPROVED]);

    Payment::factory()->forContract($contract)->percent(40)->create([
        'created_by' => $creator->id,
        'paid_at' => '2026-03-11',
        'screenshots' => [],
    ]);

    actingAs(contractViewer());

    Livewire::test(ContractPaymentsTableWidget::class, ['contractId' => $contract->id])
        ->assertOk()
        ->assertSee('40')
        ->assertSee('11.03.2026')
        ->assertSee('Kamola Rashidova');
});

it('keeps a dropped approver reachable at the foot of the chain table', function () {
    $stillIn = User::factory()->create(['name' => 'Bekzod Tursunov']);
    $dropped = User::factory()->create(['name' => 'Nilufar Ergasheva']);

    $contract = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);

    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $stillIn->id, 'order' => 1,
        'status' => ContractApprover::STATUS_PENDING,
    ]);
    // Was in the chain, got cancelled out of it — no active row left.
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $dropped->id, 'order' => 2,
        'status' => ContractApprover::STATUS_INVALIDATED,
        'original_status' => ContractApprover::STATUS_APPROVED,
    ]);

    actingAs(contractViewer());

    Livewire::test(ContractApprovalChainTableWidget::class, ['contractId' => $contract->id])
        ->assertOk()
        ->assertSee('Bekzod Tursunov')
        ->assertSee('Nilufar Ergasheva')
        ->assertSee(__('app.label.no_longer_in_chain'));
});

it('opens the counterparty dossier as a native Filament modal', function () {
    $contact = Contact::factory()->create([
        'name' => 'Silk Road Media MChJ',
        'inn' => '305671142',
        'phone' => '+998 71 205-14-77',
    ]);
    $viewer = contractViewer();
    $contract = Contract::factory()->create([
        'contact_id' => $contact->id,
        'responsible_id' => $viewer->id,
    ]);

    actingAs($viewer);

    $page = Livewire::test(ViewContract::class, ['record' => $contract->id])
        ->mountAction('contactDetails')
        ->assertActionMounted('contactDetails');

    // The hand-rolled Alpine overlay is gone — no cw-modal chrome left.
    expect($page->html())->not->toContain('cw-modal')
        ->and($page->html())->not->toContain('contactOpen');

    $modal = view('filament.resources.contracts.pages.view-contract.contact-modal', [
        'groups' => $page->instance()->contactGroups(),
    ])->render();

    expect($modal)->toContain('Silk Road Media MChJ')
        ->toContain('305671142')
        ->toContain('+998 71 205-14-77');
});
