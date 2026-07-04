<?php

use App\Filament\Resources\Contacts\Pages\ListContacts;
use App\Models\Contact;
use App\Models\Currency;
use App\Models\ProjectParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('aggregates a contact project participations by currency', function () {
    $uzs = Currency::factory()->create(['short_name' => 'UZS']);
    $usd = Currency::factory()->create(['short_name' => 'USD']);
    $contact = Contact::factory()->create();

    ProjectParticipant::factory()->create(['contact_id' => $contact->id, 'currency_id' => $uzs->id, 'amount' => 30_000_000, 'paid_amount' => 10_000_000]);
    ProjectParticipant::factory()->create(['contact_id' => $contact->id, 'currency_id' => $uzs->id, 'amount' => 20_000_000, 'paid_amount' => 20_000_000]);
    ProjectParticipant::factory()->create(['contact_id' => $contact->id, 'currency_id' => $usd->id, 'amount' => 5000, 'paid_amount' => 0]);

    $totals = $contact->projectTotalsByCurrency();

    expect($contact->projectParticipations()->count())->toBe(3)
        ->and($totals)->toHaveCount(2);

    $uzsRow = $totals->firstWhere('currency', 'UZS');

    expect($uzsRow['count'])->toBe(2)
        ->and($uzsRow['total'])->toBe(50_000_000.0)
        ->and($uzsRow['paid'])->toBe(30_000_000.0);
});

it('lists contacts with the project-participations count without error', function () {
    $contact = Contact::factory()->create();
    ProjectParticipant::factory()->count(2)->create(['contact_id' => $contact->id]);

    actingAs(userWithPermission('view_any_contact'));

    Livewire::test(ListContacts::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$contact]);
});
