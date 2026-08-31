<?php

use App\Filament\Resources\Contacts\Widgets\ContactBankAccountsTableWidget;
use App\Models\BankAccount;
use App\Models\Contact;
use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('renders the bank requisites as a native Filament table, no auto class-name heading', function () {
    $contact = Contact::factory()->create(['type' => Contact::TYPE_LEGAL]);
    $usd = Currency::factory()->create(['short_name' => 'USD']);

    BankAccount::factory()->create([
        'contact_id' => $contact->id,
        'currency_id' => $usd->id,
        'account_number' => '20208840900565085002',
        'bank_name' => 'АКБ Hamkorbank',
        'mfo' => '00083',
        'swift' => 'KHKKUZ22XXX',
    ]);

    actingAs(userWithPermission('view_any_contact', 'view_contact'));

    Livewire::test(ContactBankAccountsTableWidget::class, ['contactId' => $contact->id])
        ->assertSuccessful()
        ->assertSee('USD')
        ->assertSee('20208840900565085002')
        ->assertSee('АКБ Hamkorbank')
        ->assertSee('KHKKUZ22XXX')

        ->assertDontSee('Contact Bank Accounts Table');
});

it('shows the empty state when the contact has no bank accounts', function () {
    $contact = Contact::factory()->create(['type' => Contact::TYPE_LEGAL]);

    actingAs(userWithPermission('view_any_contact', 'view_contact'));

    Livewire::test(ContactBankAccountsTableWidget::class, ['contactId' => $contact->id])
        ->assertSuccessful()
        ->assertSee(__('app.message.no_bank_accounts'));
});
