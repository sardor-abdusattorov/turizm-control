<?php

use App\Filament\Resources\Contracts\Pages\CreateContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('opens the inline create-contact option on the contract form without the bank-accounts error', function () {
    actingAs(userWithPermission('view_any_contract', 'create_contract', 'view_any_contact', 'create_contact'));

    Livewire::test(CreateContract::class)
        ->assertOk()
        ->mountFormComponentAction('contact_id', 'createOption')
        ->assertFormComponentActionMounted('contact_id', 'createOption')
        ->assertHasNoFormComponentActionErrors();
});
