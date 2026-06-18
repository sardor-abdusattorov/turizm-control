<?php

use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Models\ContractTemplate;
use App\Models\OrderType;
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

it('disables the template select until an order type is chosen', function () {
    actAsContractCreator();

    Livewire::test(CreateContract::class)
        ->assertFormFieldExists('contract_template_id', fn ($field): bool => $field->isDisabled());
});

it('enables the template select and filters templates by the chosen order type', function () {
    actAsContractCreator();

    $typeA = OrderType::factory()->create();
    $typeB = OrderType::factory()->create();

    $forTypeA = ContractTemplate::factory()->create(['order_type_id' => $typeA->id, 'status' => true]);
    $forTypeB = ContractTemplate::factory()->create(['order_type_id' => $typeB->id, 'status' => true]);
    $general = ContractTemplate::factory()->create(['order_type_id' => null, 'status' => true]);

    Livewire::test(CreateContract::class)
        ->fillForm(['order_type_id' => $typeA->id])
        ->assertFormFieldExists('contract_template_id', function ($field) use ($forTypeA, $forTypeB, $general): bool {
            $options = $field->getOptions();

            return ! $field->isDisabled()
                && array_key_exists($forTypeA->id, $options)   // matching type
                && array_key_exists($general->id, $options)     // untyped / general
                && ! array_key_exists($forTypeB->id, $options); // other type excluded
        });
});

it('clears the chosen template when the order type changes', function () {
    actAsContractCreator();

    $typeA = OrderType::factory()->create();
    $template = ContractTemplate::factory()->create(['order_type_id' => $typeA->id, 'status' => true]);

    Livewire::test(CreateContract::class)
        ->fillForm(['order_type_id' => $typeA->id, 'contract_template_id' => $template->id])
        ->assertFormSet(['contract_template_id' => $template->id])
        ->fillForm(['order_type_id' => OrderType::factory()->create()->id])
        ->assertFormSet(['contract_template_id' => null]);
});
