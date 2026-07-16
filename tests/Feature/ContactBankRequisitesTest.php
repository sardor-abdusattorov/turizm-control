<?php

use App\Filament\Resources\Contacts\Pages\CreateContact;
use App\Filament\Resources\Contacts\Pages\EditContact;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAs(userWithPermission('view_any_contact', 'view_contact', 'create_contact', 'update_contact'));
});

it('saves a full 20-digit account, 9-digit INN and 5-digit MFO through the create form', function () {
    // Regression: these fields used to carry ->numeric()->maxLength(N), which
    // Laravel reads as "value <= N" for numeric input — so a real 20-digit
    // account (2e19) silently failed max:20 and the form would not save.
    Livewire::test(CreateContact::class)
        ->fillForm([
            'type' => Contact::TYPE_LEGAL,
            'name' => ['ru' => 'BEYOND EXPECTATIONS', 'uz' => 'BEYOND EXPECTATIONS', 'en' => 'BEYOND EXPECTATIONS'],
            'inn' => '306777698',
            'oked' => '79900',
            'bank_account' => '20208000200691000000',
            'mfo' => '01069',
            'bank_name' => 'в Яккасарайском фил. Давр банк',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $contact = Contact::query()->firstWhere('inn', '306777698');

    expect($contact)->not->toBeNull()
        ->and($contact->bank_account)->toBe('20208000200691000000')
        ->and($contact->mfo)->toBe('01069')
        ->and($contact->oked)->toBe('79900');
});

it('updates the bank account through the edit form', function () {
    $contact = Contact::factory()->create([
        'type' => Contact::TYPE_LEGAL,
        'bank_account' => '20208000200691000000',
        'phone' => null,
    ]);

    Livewire::test(EditContact::class, ['record' => $contact->id])
        ->fillForm(['bank_account' => '99988000200691007777'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($contact->fresh()->bank_account)->toBe('99988000200691007777');
});

it('rejects an account that is not exactly 20 digits', function () {
    Livewire::test(CreateContact::class)
        ->fillForm([
            'type' => Contact::TYPE_LEGAL,
            'name' => 'Short account co',
            'bank_account' => '123',
        ])
        ->call('create')
        ->assertHasFormErrors(['bank_account']);
});

it('leaves the bank fields optional for a foreign counterparty', function () {
    // Foreigners without an Uzbek INN/account must still save.
    Livewire::test(CreateContact::class)
        ->fillForm([
            'type' => Contact::TYPE_LEGAL,
            'name' => ['ru' => 'RX France', 'uz' => 'RX France', 'en' => 'RX France'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Contact::query()->where('name->ru', 'RX France')->exists())->toBeTrue();
});
