<?php

use App\Enums\ContractStatus;
use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function legacySwitchContract(ContractStatus $status): Contract
{
    return Contract::factory()->create([
        'status' => $status,
        'contract_type_id' => ContractType::factory()->create()->id,
        'contact_id' => Contact::factory()->create(['status' => true])->id,
        'currency_id' => Currency::factory()->create(['status' => true])->id,
    ]);
}

function legacySwitchAuthor(Contract $contract): User
{
    $user = $contract->responsible;

    foreach (['view_any_contract', 'view_contract', 'update_contract', 'create_contract'] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }

    actingAs($user->fresh());

    return $user;
}

function queuedChainFor(Contract $contract, int $count = 2): void
{
    foreach (range(1, $count) as $order) {
        $contract->approvers()->create([
            'user_id' => User::factory()->create(['status' => User::STATUS_ACTIVE])->id,
            'order' => $order,
            'status' => ContractApprover::STATUS_QUEUED,
        ]);
    }
}

it('converts a mistakenly-normal draft into a legacy signed contract from the edit page', function () {
    Storage::fake('local');

    $contract = legacySwitchContract(Contract::STATUS_DRAFT);
    queuedChainFor($contract);
    legacySwitchAuthor($contract);

    Livewire::test(EditContract::class, ['record' => $contract->id])
        ->fillForm([
            'already_signed' => true,
            'signed_at' => '2025-03-14',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $contract->refresh();

    expect($contract->status)->toBe(Contract::STATUS_APPROVED)
        ->and($contract->signed_at?->format('Y-m-d'))->toBe('2025-03-14')
        ->and($contract->activeApprovers()->count())->toBe(0)
        ->and($contract->approvers()->where('status', ContractApprover::STATUS_INVALIDATED)->count())->toBe(2);
});

it('keeps decided verdicts as audit when an in-review contract is switched to legacy', function () {
    Storage::fake('local');

    $contract = legacySwitchContract(Contract::STATUS_IN_REVIEW);

    $decided = $contract->approvers()->create([
        'user_id' => User::factory()->create(['status' => User::STATUS_ACTIVE])->id,
        'order' => 1,
        'status' => ContractApprover::STATUS_APPROVED,
        'comment' => 'Проверено юристом',
        'acted_at' => now(),
    ]);
    $contract->approvers()->create([
        'user_id' => User::factory()->create(['status' => User::STATUS_ACTIVE])->id,
        'order' => 2,
        'status' => ContractApprover::STATUS_PENDING,
    ]);

    legacySwitchAuthor($contract);

    Livewire::test(EditContract::class, ['record' => $contract->id])
        ->fillForm([
            'already_signed' => true,
            'signed_at' => '2024-11-02',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $contract->refresh();

    expect($contract->status)->toBe(Contract::STATUS_APPROVED)
        ->and($decided->fresh()->status)->toBe(ContractApprover::STATUS_APPROVED)
        ->and($decided->fresh()->comment)->toBe('Проверено юристом')
        ->and($contract->approvers()->where('status', ContractApprover::STATUS_PENDING)->count())->toBe(0);
});

it('lets the author fix fields on an approved legacy contract without losing the status', function () {
    Storage::fake('local');

    $contract = legacySwitchContract(Contract::STATUS_APPROVED);
    $contract->forceFill(['signed_at' => '2024-05-20'])->saveQuietly();
    $author = legacySwitchAuthor($contract);

    // Reopening a filed contract takes the dedicated permission on top of
    // the usual update rights.
    Permission::findOrCreate('update_approved_contract', 'web');
    $author->givePermissionTo('update_approved_contract');
    actingAs($author->fresh());

    // The switch prefills ON for an approved contract — a plain typo fix must
    // keep the status without the author touching anything else. title is a
    // reapproval-trigger field: without preserveApprovedOnNextSave the
    // observer would bounce the contract to draft and build a junk chain.
    Livewire::test(EditContract::class, ['record' => $contract->id])
        ->assertFormSet(['already_signed' => true])
        ->fillForm([
            'title' => 'Исправленное наименование',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $contract->refresh();

    expect($contract->status)->toBe(Contract::STATUS_APPROVED)
        ->and($contract->title)->toBe('Исправленное наименование')
        ->and($contract->signed_at?->format('Y-m-d'))->toBe('2024-05-20')
        ->and($contract->approvers()->count())->toBe(0);
});

it('refuses the edit page for the author without the reopen permission', function () {
    $contract = legacySwitchContract(Contract::STATUS_APPROVED);
    legacySwitchAuthor($contract);

    Livewire::test(EditContract::class, ['record' => $contract->id])
        ->assertForbidden();
});

it('never lets a fresh contract inherit files from a recycled id', function () {
    Storage::fake('local');

    // A rebuilt database reuses ids while old folders survive on disk — the
    // first contract this test creates will get id 1, so plant a stranger's
    // draft there beforehand.
    Storage::disk('local')->put('uploads/files/contracts/1/draft.docx', 'ghost from a previous database');

    $contractType = ContractType::factory()->create();
    $contact = Contact::factory()->create(['status' => true]);
    $currency = Currency::factory()->create(['status' => true]);

    $creator = User::factory()->create(['status' => User::STATUS_ACTIVE]);
    foreach (['view_any_contract', 'create_contract'] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $creator->givePermissionTo($ability);
    }
    actingAs($creator->fresh());

    Livewire::test(CreateContract::class)
        ->fillForm([
            'already_signed' => true,
            'signed_at' => '2025-01-10',
            'number' => 'CLEAN-001',
            'contract_type_id' => $contractType->id,
            'contact_id' => $contact->id,
            'title' => 'Договор без шаблона',
            'amount' => 1000,
            'currency_id' => $currency->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $contract = Contract::query()->firstWhere('number', 'CLEAN-001');

    expect($contract->id)->toBe(1)
        ->and($contract->documentExists())->toBeFalse();

    Storage::disk('local')->assertMissing('uploads/files/contracts/1/draft.docx');
});
