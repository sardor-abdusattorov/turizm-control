<?php

use App\Filament\Resources\Contracts\Pages\CreateContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

// Regression: the contact-picker on the contract form reuses ContactForm as its
// create-option form. ContactForm carries a bankAccounts *relationship* repeater
// which cannot bind inside a create-option (there is no saved Contact yet), so it
// used to 500 with "relationship [bankAccounts] does not exist on model [Contract]".
it('opens the inline create-contact option on the contract form without the bank-accounts error', function () {
    actingAs(userWithPermission('view_any_contract', 'create_contract', 'view_any_contact', 'create_contact'));

    Livewire::test(CreateContract::class)
        ->assertOk()
        ->mountFormComponentAction('contact_id', 'createOption')
        ->assertFormComponentActionMounted('contact_id', 'createOption')
        ->assertHasNoFormComponentActionErrors();
});
