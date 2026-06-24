<?php

use App\Filament\Resources\Contacts\Pages\ListContacts;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('summarises a contact contracts per currency for a viewer who sees all', function () {
    $usd = Currency::factory()->create(['short_name' => 'USD']);
    $eur = Currency::factory()->create(['short_name' => 'EUR']);
    $contact = Contact::factory()->create();

    Contract::factory()->count(2)->create([
        'contact_id' => $contact->id,
        'currency_id' => $usd->id,
        'amount' => 1000,
        'status' => Contract::STATUS_APPROVED,
    ]);
    Contract::factory()->create([
        'contact_id' => $contact->id,
        'currency_id' => $eur->id,
        'amount' => 500,
        'status' => Contract::STATUS_APPROVED,
    ]);

    $viewer = userWithPermission('view_any_contact', 'view_all_contracts');

    $totals = $contact->contractTotalsByCurrency($viewer)->keyBy('currency');

    expect($totals->get('USD'))->toMatchArray(['count' => 2, 'total' => 2000.0])
        ->and($totals->get('EUR'))->toMatchArray(['count' => 1, 'total' => 500.0]);
});

it('scopes the breakdown to the contracts the viewer is allowed to see', function () {
    $usd = Currency::factory()->create(['short_name' => 'USD']);
    $contact = Contact::factory()->create();
    $manager = userWithPermission('view_any_contact');

    // The manager's own contract — visible.
    Contract::factory()->create([
        'contact_id' => $contact->id,
        'currency_id' => $usd->id,
        'amount' => 1000,
        'status' => Contract::STATUS_APPROVED,
        'responsible_id' => $manager->id,
    ]);

    // Someone else's contract with the same counterparty — must not leak in.
    Contract::factory()->create([
        'contact_id' => $contact->id,
        'currency_id' => $usd->id,
        'amount' => 9999,
        'status' => Contract::STATUS_APPROVED,
        'responsible_id' => User::factory()->create()->id,
    ]);

    $totals = $contact->contractTotalsByCurrency($manager);

    expect($totals)->toHaveCount(1)
        ->and($totals->first())->toMatchArray(['currency' => 'USD', 'count' => 1, 'total' => 1000.0]);
});

it('renders the contracts-count column on the contacts list without error', function () {
    actingAs(userWithPermission('view_any_contact', 'view_all_contracts'));

    $usd = Currency::factory()->create(['short_name' => 'USD']);
    $contact = Contact::factory()->create();
    Contract::factory()->count(3)->create([
        'contact_id' => $contact->id,
        'currency_id' => $usd->id,
        'status' => Contract::STATUS_APPROVED,
    ]);

    Livewire::test(ListContacts::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$contact])
        ->assertTableColumnStateSet('contracts_count', 3, record: $contact);
});
