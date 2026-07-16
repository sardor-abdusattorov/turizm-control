<?php

use App\Filament\Resources\Contacts\Pages\ListContacts;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('flags a counterparty as supplier and/or client from its contract directions', function () {
    $expenseType = ContractType::factory()->create();          // Expense — we pay them
    $incomeType = ContractType::factory()->income()->create(); // Income — they pay us

    $supplier = Contact::factory()->create();
    $client = Contact::factory()->create();
    $both = Contact::factory()->create();
    $neither = Contact::factory()->create();

    Contract::factory()->create(['contact_id' => $supplier->id, 'contract_type_id' => $expenseType->id]);
    Contract::factory()->create(['contact_id' => $client->id, 'contract_type_id' => $incomeType->id]);
    Contract::factory()->create(['contact_id' => $both->id, 'contract_type_id' => $expenseType->id]);
    Contract::factory()->create(['contact_id' => $both->id, 'contract_type_id' => $incomeType->id]);

    $flags = Contact::query()->withRoleFlags()->get()->keyBy('id');

    expect((bool) $flags[$supplier->id]->is_supplier)->toBeTrue()
        ->and((bool) $flags[$supplier->id]->is_client)->toBeFalse()
        ->and((bool) $flags[$client->id]->is_supplier)->toBeFalse()
        ->and((bool) $flags[$client->id]->is_client)->toBeTrue()
        ->and((bool) $flags[$both->id]->is_supplier)->toBeTrue()
        ->and((bool) $flags[$both->id]->is_client)->toBeTrue()
        ->and((bool) $flags[$neither->id]->is_supplier)->toBeFalse()
        ->and((bool) $flags[$neither->id]->is_client)->toBeFalse();
});

it('filters the contacts list by supplier / client role', function () {
    actingAs(userWithPermission('view_any_contact'));

    $expenseType = ContractType::factory()->create();
    $incomeType = ContractType::factory()->income()->create();

    $supplier = Contact::factory()->create();
    $client = Contact::factory()->create();

    Contract::factory()->create(['contact_id' => $supplier->id, 'contract_type_id' => $expenseType->id]);
    Contract::factory()->create(['contact_id' => $client->id, 'contract_type_id' => $incomeType->id]);

    Livewire::test(ListContacts::class)
        ->filterTable('role', 'supplier')
        ->assertCanSeeTableRecords([$supplier])
        ->assertCanNotSeeTableRecords([$client])
        ->filterTable('role', 'client')
        ->assertCanSeeTableRecords([$client])
        ->assertCanNotSeeTableRecords([$supplier]);
});

it('renders the localized role badge on the contacts list', function () {
    actingAs(userWithPermission('view_any_contact'));

    $expenseType = ContractType::factory()->create();
    $supplier = Contact::factory()->create();
    Contract::factory()->create(['contact_id' => $supplier->id, 'contract_type_id' => $expenseType->id]);

    Livewire::test(ListContacts::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$supplier])
        ->assertSee(__('app.label.role_supplier'));
});
