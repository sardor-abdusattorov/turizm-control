<?php

use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Models\Contact;
use App\Models\ContractType;
use App\Models\Sponsor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function counterpartySearchActing(): User
{
    $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);

    foreach (['view_any_contract', 'view_contract', 'create_contract'] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }

    actingAs($user->fresh());

    return $user;
}

it('finds a contact by its tax id, not just its name', function () {
    $target = Contact::factory()->create([
        'name' => 'ORIENT VOYAGE',
        'inn' => '305667788',
        'status' => true,
    ]);
    Contact::factory()->create(['name' => 'SILK ROAD TOURS', 'inn' => '301112233', 'status' => true]);

    expect(array_keys(Contact::searchOptions('305667788')))->toBe([$target->id])
        ->and(array_keys(Contact::searchOptions('ORIENT')))->toBe([$target->id]);
});

it('finds an individual contact by PINFL', function () {
    $guide = Contact::factory()->create([
        'name' => 'Гид Азизов',
        'type' => Contact::TYPE_INDIVIDUAL,
        'inn' => null,
        'pinfl' => '31234567890123',
        'status' => true,
    ]);

    expect(array_keys(Contact::searchOptions('31234567890123')))->toBe([$guide->id]);
});

it('finds a sponsor by its tax id', function () {
    $target = Sponsor::factory()->create(['name' => 'UZBEKISTAN AIRWAYS', 'inn' => '200334455', 'status' => true]);
    Sponsor::factory()->create(['name' => 'HUMO ARENA', 'inn' => '200998877', 'status' => true]);

    expect(array_keys(Sponsor::searchOptions('200334455')))->toBe([$target->id]);
});

it('shows the tax id beside the name in the option label', function () {
    $contact = Contact::factory()->create(['name' => 'ORIENT VOYAGE', 'inn' => '305667788', 'status' => true]);

    expect($contact->optionLabel())
        ->toContain('ORIENT VOYAGE')
        ->toContain('305667788')
        ->toContain(__('app.label.inn'));
});

it('escapes a counterparty name in the HTML option label', function () {
    $contact = Contact::factory()->create([
        'name' => '<script>alert(1)</script>',
        'inn' => '309999999',
        'status' => true,
    ]);

    expect($contact->optionLabel())
        ->not->toContain('<script>')
        ->toContain('&lt;script&gt;');
});

it('keeps archived counterparties out of the picker', function () {
    $archived = Contact::factory()->create(['name' => 'CLOSED AGENCY', 'inn' => '300000001', 'status' => false]);

    expect(array_keys(Contact::searchOptions('300000001')))->not->toContain($archived->id)
        ->and(array_keys(Contact::searchOptions()))->not->toContain($archived->id);
});

it('searches the counterparty picker on the contract form', function () {
    counterpartySearchActing();

    $target = Contact::factory()->create(['name' => 'ORIENT VOYAGE', 'inn' => '305667788', 'status' => true]);
    ContractType::factory()->create();

    Livewire::test(CreateContract::class)
        ->assertFormFieldExists('contact_id', fn ($field): bool => $field->isSearchable())
        ->assertFormFieldExists(
            'contact_id',
            fn ($field): bool => array_key_exists($target->id, $field->getSearchResults('305667788')),
        );
});

it('refuses a second contact with the same tax id', function () {
    Contact::factory()->create(['inn' => '305667788', 'status' => true]);

    expect(fn () => Contact::factory()->create(['inn' => '305667788']))->toThrow(Exception::class);
});

it('refuses a second sponsor with the same tax id', function () {
    Sponsor::factory()->create(['inn' => '200334455', 'status' => true]);

    expect(fn () => Sponsor::factory()->create(['inn' => '200334455']))->toThrow(Exception::class);
});

it('rejects a duplicate sponsor tax id on the form before it reaches the database', function () {
    Sponsor::factory()->create(['inn' => '200334455', 'status' => true]);

    $validator = validator(
        ['inn' => '200334455'],
        ['inn' => Rule::unique('sponsors', 'inn')],
    );

    expect(fn () => $validator->validate())->toThrow(ValidationException::class);
});
