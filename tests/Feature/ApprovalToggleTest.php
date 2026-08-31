<?php

use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Filament\Resources\Contracts\Pages\ViewContract;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function setApprovalEnabled(bool $enabled): void
{
    Settings::set('approval.enabled', $enabled);
}

function contractAuthor(): User
{
    $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);

    foreach (['view_any_contract', 'view_contract', 'create_contract', 'update_contract'] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }

    actingAs($user->fresh());

    return $user;
}

it('creates a contract without an approval chain when approval is off', function () {
    setApprovalEnabled(false);
    contractAuthor();

    $type = ContractType::factory()->create();
    $contact = Contact::factory()->create();
    $currency = Currency::factory()->create();

    Livewire::test(CreateContract::class)
        ->fillForm([
            'number' => 'SCAN-2025-001',
            'contract_type_id' => $type->id,
            'contact_id' => $contact->id,
            'title' => 'Аренда площади — подписанный скан',
            'currency_id' => $currency->id,
            'amount' => 16595.41,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $contract = Contract::firstWhere('number', 'SCAN-2025-001');

    expect($contract)->not->toBeNull()
        ->and($contract->approvers()->count())->toBe(0)
        ->and($contract->canBeSubmittedBy($contract->responsible))->toBeFalse();
});

it('marks a draft as signed through the page action when approval is off', function () {
    setApprovalEnabled(false);

    $contract = Contract::factory()->create();
    $user = $contract->responsible;

    foreach (['view_any_contract', 'view_contract', 'update_contract'] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }

    actingAs($user->fresh());

    Livewire::test(ViewContract::class, ['record' => $contract->id])
        ->callAction('markSigned', ['signed_at' => '2025-02-17'])
        ->assertHasNoActionErrors();

    $contract->refresh();

    expect($contract->status)->toBe(Contract::STATUS_APPROVED)
        ->and($contract->signed_at?->format('Y-m-d'))->toBe('2025-02-17');
});

it('keeps the signing action hidden while approval is on', function () {
    setApprovalEnabled(true);

    $contract = Contract::factory()->create();
    $user = $contract->responsible;

    foreach (['view_any_contract', 'view_contract', 'update_contract'] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }

    actingAs($user->fresh());

    Livewire::test(ViewContract::class, ['record' => $contract->id])
        ->assertActionHidden('markSigned');
});
