<?php

use App\Filament\Resources\Contacts\Pages\ListContacts;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('lets a user with edit rights toggle a status straight from the list', function () {
    actingAs(userWithPermission('view_any_contact', 'update_contact'));

    $contact = Contact::factory()->create(['status' => true]);

    Livewire::test(ListContacts::class)
        ->call('updateTableColumnState', 'status', (string) $contact->getKey(), false);

    expect($contact->fresh()->status)->toBeFalse();
});

it('blocks the status toggle for a user without edit rights', function () {
    actingAs(userWithPermission('view_any_contact'));

    $contact = Contact::factory()->create(['status' => true]);

    Livewire::test(ListContacts::class)
        ->call('updateTableColumnState', 'status', (string) $contact->getKey(), false);

    // Rejected server-side — Filament short-circuits the write on a disabled
    // column, so the status is untouched even though the request went through.
    expect($contact->fresh()->status)->toBeTrue();
});
