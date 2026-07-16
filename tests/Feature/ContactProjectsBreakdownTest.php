<?php

use App\Filament\Resources\Contacts\Pages\ListContacts;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('aggregates a contact income contracts by currency', function () {
    // visibleTo() drives the totals, so the viewer must be able to see the
    // (non-draft) income contracts — view_all_contracts sees the whole pipeline.
    actingAs(userWithPermission('view_all_contracts'));

    $uzs = Currency::factory()->create(['short_name' => 'UZS']);
    $usd = Currency::factory()->create(['short_name' => 'USD']);
    $contact = Contact::factory()->create();
    $feeType = ContractType::factory()->income()->create();

    // paidAmount() = amount * paid_percent / 100 (contracts carry no absolute
    // paid column), so these percents reproduce the old participant paid sums:
    // UZS paid = 15M + 15M = 30M, USD paid = 0.
    Contract::factory()->create([
        'contact_id' => $contact->id,
        'contract_type_id' => $feeType->id,
        'currency_id' => $uzs->id,
        'amount' => 30_000_000,
        'paid_percent' => 50,
        'status' => Contract::STATUS_APPROVED,
    ]);
    Contract::factory()->create([
        'contact_id' => $contact->id,
        'contract_type_id' => $feeType->id,
        'currency_id' => $uzs->id,
        'amount' => 20_000_000,
        'paid_percent' => 75,
        'status' => Contract::STATUS_APPROVED,
    ]);
    Contract::factory()->create([
        'contact_id' => $contact->id,
        'contract_type_id' => $feeType->id,
        'currency_id' => $usd->id,
        'amount' => 5000,
        'paid_percent' => 0,
        'status' => Contract::STATUS_APPROVED,
    ]);

    $totals = $contact->projectTotalsByCurrency();

    expect($contact->incomeContracts()->count())->toBe(3)
        ->and($totals)->toHaveCount(2);

    $uzsRow = $totals->firstWhere('currency', 'UZS');

    expect($uzsRow['count'])->toBe(2)
        ->and($uzsRow['total'])->toBe(50_000_000.0)
        ->and($uzsRow['paid'])->toBe(30_000_000.0);
});

it('lists contacts with the income-contracts count without error', function () {
    $contact = Contact::factory()->create();
    Contract::factory()->count(2)->create([
        'contact_id' => $contact->id,
        'contract_type_id' => ContractType::factory()->income(),
        'status' => Contract::STATUS_APPROVED,
    ]);

    actingAs(userWithPermission('view_any_contact', 'view_all_contracts'));

    Livewire::test(ListContacts::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$contact]);
});
