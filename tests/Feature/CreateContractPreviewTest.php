<?php

use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Models\Contact;
use App\Models\ContractTemplate;
use App\Models\Currency;
use App\Models\OrderType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function previewAuthor(): User
{
    $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
    foreach (['view_any_contract', 'create_contract'] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }
    actingAs($user->fresh());

    return $user;
}

it('renders the create-contract page with an empty live preview pane', function () {
    previewAuthor();

    $html = Livewire::test(CreateContract::class)->html();

    expect($html)
        ->toContain('Contract preview')
        ->toContain('Untitled contract')
        ->toContain('No amount yet')
        ->toContain('No approvers selected');
});

it('updates the live preview as the form is filled', function () {
    previewAuthor();

    $orderType = OrderType::factory()->create(['title' => 'HR memo']);
    $template = ContractTemplate::factory()->create([
        'order_type_id' => $orderType->id,
        'name' => 'Standard NDA',
        'status' => true,
    ]);
    $contact = Contact::factory()->create([
        'name' => 'Oltin Vodiy LLC',
        'status' => true,
    ]);
    $currency = Currency::factory()->create([
        'short_name' => 'UZS',
        'status' => true,
    ]);

    $html = Livewire::test(CreateContract::class)
        ->fillForm([
            'number' => 'C-2026-007',
            'title' => 'Marketing services',
            'amount' => 15000000,
            'order_type_id' => $orderType->id,
            'contract_template_id' => $template->id,
            'contact_id' => $contact->id,
            'currency_id' => $currency->id,
        ])
        ->html();

    expect($html)
        ->toContain('C-2026-007')
        ->toContain('Marketing services')
        ->toContain('15 000 000.00')
        ->toContain('UZS')
        ->toContain('HR memo')
        ->toContain('Standard NDA')
        ->toContain('Oltin Vodiy LLC');
});
