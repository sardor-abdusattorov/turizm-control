<?php

use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function actAsContractCreator(): User
{
    $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);

    foreach (['view_any_contract', 'create_contract'] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }

    actingAs($user->fresh());

    return $user;
}

it('disables the template select until a contract type is chosen', function () {
    actAsContractCreator();

    Livewire::test(CreateContract::class)
        ->assertFormFieldExists('contract_template_id', fn ($field): bool => $field->isDisabled());
});

it('enables the template select and filters templates by the chosen contract type', function () {
    actAsContractCreator();

    $typeA = ContractType::factory()->create();
    $typeB = ContractType::factory()->create();

    $forTypeA = ContractTemplate::factory()->create(['contract_type_id' => $typeA->id, 'status' => true]);
    $forTypeB = ContractTemplate::factory()->create(['contract_type_id' => $typeB->id, 'status' => true]);
    $general = ContractTemplate::factory()->create(['contract_type_id' => null, 'status' => true]);

    Livewire::test(CreateContract::class)
        ->fillForm(['contract_type_id' => $typeA->id])
        ->assertFormFieldExists('contract_template_id', function ($field) use ($forTypeA, $forTypeB, $general): bool {
            $options = $field->getOptions();

            return ! $field->isDisabled()
                && array_key_exists($forTypeA->id, $options)   // matching kind
                && array_key_exists($general->id, $options)     // untyped / general
                && ! array_key_exists($forTypeB->id, $options); // other kind excluded
        });
});

it('clears the chosen template when the contract type changes', function () {
    actAsContractCreator();

    $typeA = ContractType::factory()->create();
    $template = ContractTemplate::factory()->create(['contract_type_id' => $typeA->id, 'status' => true]);

    Livewire::test(CreateContract::class)
        ->fillForm(['contract_type_id' => $typeA->id, 'contract_template_id' => $template->id])
        ->assertFormSet(['contract_template_id' => $template->id])
        ->fillForm(['contract_type_id' => ContractType::factory()->create()->id])
        ->assertFormSet(['contract_template_id' => null]);
});

it('keeps the template optional while requiring the contract type', function () {
    actAsContractCreator();

    Livewire::test(CreateContract::class)
        ->assertFormFieldExists('contract_type_id', fn ($field): bool => $field->isRequired())
        ->assertFormFieldExists('contract_template_id', fn ($field): bool => ! $field->isRequired())
        // The basis order moved to the project form — the contract no longer asks.
        ->assertFormFieldDoesNotExist('order_id');
});
