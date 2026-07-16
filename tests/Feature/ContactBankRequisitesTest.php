<?php

use App\Filament\Resources\Contacts\Pages\CreateContact;
use App\Filament\Resources\Contacts\Pages\EditContact;
use App\Models\BankAccount;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Currency;
use App\Services\Documents\ContractPlaceholderValues;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAs(userWithPermission('view_any_contact', 'view_contact', 'create_contact', 'update_contact'));
});

it('saves a bank account through the repeater on create', function () {
    Livewire::test(CreateContact::class)
        ->fillForm([
            'type' => Contact::TYPE_LEGAL,
            'name' => ['ru' => 'BEYOND EXPECTATIONS', 'uz' => 'BEYOND EXPECTATIONS', 'en' => 'BEYOND EXPECTATIONS'],
            'inn' => '306777698',
            'bankAccounts' => [
                [
                    'currency_id' => null,
                    'account_number' => '20208000200691000000',
                    'bank_name' => 'в Яккасарайском фил. Давр банк',
                    'mfo' => '01069',
                    'swift' => null,
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $contact = Contact::query()->firstWhere('inn', '306777698');
    $account = $contact?->bankAccounts()->first();

    expect($account)->not->toBeNull()
        ->and($account->account_number)->toBe('20208000200691000000')
        ->and($account->mfo)->toBe('01069');
});

it('stores several accounts, one per currency', function () {
    $uzs = Currency::factory()->create(['short_name' => 'UZS']);
    $eur = Currency::factory()->create(['short_name' => 'EUR']);

    Livewire::test(CreateContact::class)
        ->fillForm([
            'type' => Contact::TYPE_LEGAL,
            'name' => ['ru' => 'ZAMIN DMC', 'uz' => 'ZAMIN DMC', 'en' => 'ZAMIN DMC'],
            'inn' => '311343097',
            'bankAccounts' => [
                ['currency_id' => $uzs->id, 'account_number' => '20208000107076480001', 'bank_name' => 'Капитал банк', 'mfo' => '00445', 'swift' => null],
                ['currency_id' => $eur->id, 'account_number' => '20208978807076480001', 'bank_name' => 'Капитал банк', 'mfo' => '00445', 'swift' => null],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $contact = Contact::query()->firstWhere('inn', '311343097');

    expect($contact->bankAccounts)->toHaveCount(2);
});

it('updates an account through the edit form', function () {
    $contact = Contact::factory()->create(['type' => Contact::TYPE_LEGAL, 'phone' => null]);
    $account = BankAccount::factory()->create([
        'contact_id' => $contact->id,
        'account_number' => '20208000200691000000',
    ]);

    Livewire::test(EditContact::class, ['record' => $contact->id])
        ->fillForm([
            'bankAccounts' => [
                [
                    'currency_id' => null,
                    'account_number' => '99988000200691007777',
                    'bank_name' => $account->bank_name,
                    'mfo' => '01069',
                    'swift' => null,
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($contact->fresh()->bankAccounts()->first()->account_number)->toBe('99988000200691007777');
});

it('rejects an account that is too short', function () {
    Livewire::test(CreateContact::class)
        ->fillForm([
            'type' => Contact::TYPE_LEGAL,
            'name' => ['ru' => 'Short co', 'uz' => 'Short co', 'en' => 'Short co'],
            'bankAccounts' => [
                ['currency_id' => null, 'account_number' => '123', 'bank_name' => null, 'mfo' => null, 'swift' => null],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors();
});

it('leaves bank accounts optional for a foreign counterparty', function () {
    Livewire::test(CreateContact::class)
        ->fillForm([
            'type' => Contact::TYPE_LEGAL,
            'name' => ['ru' => 'RX France', 'uz' => 'RX France', 'en' => 'RX France'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Contact::query()->where('name->ru', 'RX France')->exists())->toBeTrue();
});

it('picks the account matching the deal currency, falling back to a generic one', function () {
    $eur = Currency::factory()->create(['short_name' => 'EUR']);
    $contact = Contact::factory()->create(['type' => Contact::TYPE_LEGAL]);

    $generic = BankAccount::factory()->create(['contact_id' => $contact->id, 'currency_id' => null, 'account_number' => 'GENERIC-0000000000000', 'sort' => 0]);
    $euro = BankAccount::factory()->create(['contact_id' => $contact->id, 'currency_id' => $eur->id, 'account_number' => 'EURO-00000000000000000', 'sort' => 1]);

    expect($contact->bankAccountFor($eur->id)?->id)->toBe($euro->id)
        ->and($contact->bankAccountFor(999)?->id)->toBe($generic->id);
});

it('feeds the currency-matched account into the contract document placeholders', function () {
    $eur = Currency::factory()->create(['short_name' => 'EUR']);
    $contact = Contact::factory()->create(['type' => Contact::TYPE_LEGAL]);

    BankAccount::factory()->create(['contact_id' => $contact->id, 'currency_id' => null, 'account_number' => 'UZS-ACCOUNT-000000000', 'sort' => 0]);
    BankAccount::factory()->create(['contact_id' => $contact->id, 'currency_id' => $eur->id, 'account_number' => 'EUR-ACCOUNT-000000000', 'sort' => 1]);

    $contract = Contract::factory()->create([
        'contact_id' => $contact->id,
        'currency_id' => $eur->id,
    ]);

    $values = app(ContractPlaceholderValues::class)->for($contract);

    expect($values['contact.bank_account'])->toBe('EUR-ACCOUNT-000000000');
});
