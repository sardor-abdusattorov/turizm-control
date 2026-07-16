<?php

use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Sponsor;
use App\Services\Documents\ContractPlaceholderValues;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('knows which contract types face a sponsor', function () {
    $sponsorship = ContractType::factory()->sponsorship()->create();
    $fee = ContractType::factory()->income()->create();
    $rental = ContractType::factory()->create();

    expect($sponsorship->usesSponsor())->toBeTrue()
        ->and($fee->usesSponsor())->toBeFalse()
        ->and($rental->usesSponsor())->toBeFalse()
        ->and(ContractType::sponsorFacingIds())->toBe([$sponsorship->id]);
});

it('derives the collected amount from the paid percent', function () {
    $contract = Contract::factory()->make(['amount' => 1000, 'paid_percent' => 25]);

    expect($contract->paidAmount())->toBe(250.0);
});

it('resolves the counterparty to the sponsor on a sponsorship contract', function () {
    $sponsorContract = Contract::factory()->sponsorship()->create();
    $contactContract = Contract::factory()->create();

    expect($sponsorContract->counterparty())->toBeInstanceOf(Sponsor::class)
        ->and($sponsorContract->contact_id)->toBeNull()
        ->and($contactContract->counterparty())->toBeInstanceOf(Contact::class)
        ->and($contactContract->sponsor_id)->toBeNull();
});

it('shows the sponsor picker for a sponsorship type and the contact picker otherwise', function () {
    actingAs(userWithPermission('view_any_contract', 'create_contract'));

    $sponsorship = ContractType::factory()->sponsorship()->create();
    $fee = ContractType::factory()->income()->create();

    Livewire::test(CreateContract::class)
        ->fillForm(['contract_type_id' => $sponsorship->id])
        ->assertFormFieldIsHidden('contact_id')
        ->assertFormFieldIsVisible('sponsor_id')
        ->fillForm(['contract_type_id' => $fee->id])
        ->assertFormFieldIsVisible('contact_id')
        ->assertFormFieldIsHidden('sponsor_id');
});

it('files a sponsorship contract against a sponsor, leaving the contact empty', function () {
    Storage::fake('local');
    actingAs(userWithPermission('view_any_contract', 'create_contract'));

    $sponsorship = ContractType::factory()->sponsorship()->create();
    $sponsor = Sponsor::factory()->create(['status' => true]);
    $currency = Currency::factory()->create(['status' => true]);

    Livewire::test(CreateContract::class)
        ->fillForm([
            'already_signed' => true,
            'signed_at' => '2026-02-10',
            'number' => 'SPON-001',
            'contract_type_id' => $sponsorship->id,
            'sponsor_id' => $sponsor->id,
            'title' => 'Спонсорский вклад',
            'amount' => 90_000_000,
            'currency_id' => $currency->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $contract = Contract::where('number', 'SPON-001')->firstOrFail();

    expect($contract->sponsor_id)->toBe($sponsor->id)
        ->and($contract->contact_id)->toBeNull();
});

it('files a fee contract against a contact, leaving the sponsor empty', function () {
    Storage::fake('local');
    actingAs(userWithPermission('view_any_contract', 'create_contract'));

    $fee = ContractType::factory()->income()->create();
    $contact = Contact::factory()->create(['status' => true]);
    $currency = Currency::factory()->create(['status' => true]);

    Livewire::test(CreateContract::class)
        ->fillForm([
            'already_signed' => true,
            'signed_at' => '2026-02-10',
            'number' => 'FEE-001',
            'contract_type_id' => $fee->id,
            'contact_id' => $contact->id,
            'title' => 'Взнос участника',
            'amount' => 40_000_000,
            'currency_id' => $currency->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $contract = Contract::where('number', 'FEE-001')->firstOrFail();

    expect($contract->contact_id)->toBe($contact->id)
        ->and($contract->sponsor_id)->toBeNull();
});

it('builds the document placeholders for a sponsor contract without a contact', function () {
    $contract = Contract::factory()->sponsorship()->create(['amount' => 50_000_000]);

    $values = app(ContractPlaceholderValues::class)->for($contract);

    // No contact behind a sponsorship contract — the contact.* placeholders
    // fall back to empty strings instead of throwing.
    expect($values['contact.name'])->toBe('')
        ->and($values['contact.bank_account'])->toBe('')
        ->and($values['amount'])->toBe('50 000 000.00');
});
