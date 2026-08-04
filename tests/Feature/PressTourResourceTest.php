<?php

use App\Enums\PressTourDirection;
use App\Filament\Resources\PressTours\Pages\CreatePressTour;
use App\Filament\Resources\PressTours\Pages\EditPressTour;
use App\Filament\Resources\PressTours\Pages\ListPressTours;
use App\Filament\Resources\PressTours\Pages\ViewPressTour;
use App\Filament\Resources\PressTours\PressTourResource;
use App\Models\PressTour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

it('lists press tours for a user holding the permission', function () {
    actingAs(userWithPermission('view_any_press_tour', 'view_press_tour'));

    $tours = PressTour::factory()->count(3)->create();

    Livewire::test(ListPressTours::class)
        ->assertCanSeeTableRecords($tours);
});

it('splits the list into the registry three directions', function () {
    actingAs(userWithPermission('view_any_press_tour', 'view_press_tour'));

    $inbound = PressTour::factory()->inbound()->create();
    $local = PressTour::factory()->create();

    Livewire::test(ListPressTours::class)
        ->set('activeTab', PressTourDirection::Inbound->value)
        ->assertCanSeeTableRecords([$inbound])
        ->assertCanNotSeeTableRecords([$local]);
});

it('filters the list by direction', function () {
    actingAs(userWithPermission('view_any_press_tour', 'view_press_tour'));

    $outbound = PressTour::factory()->outbound()->create();
    $local = PressTour::factory()->create();

    Livewire::test(ListPressTours::class)
        ->filterTable('direction', PressTourDirection::Outbound->value)
        ->assertCanSeeTableRecords([$outbound])
        ->assertCanNotSeeTableRecords([$local]);
});

it('shows the registry headcount wording in the table', function () {
    actingAs(userWithPermission('view_any_press_tour', 'view_press_tour'));

    PressTour::factory()->create([
        'name' => 'Форум СМИ',
        'people_count' => null,
        'people_note' => '6+11',
    ]);

    Livewire::test(ListPressTours::class)->assertSee('6+11');
});

it('creates a press tour', function () {
    actingAs(userWithPermission('view_any_press_tour', 'create_press_tour'));

    Livewire::test(CreatePressTour::class)
        ->fillForm([
            'direction' => PressTourDirection::Inbound->value,
            'name' => 'Визит СМИ Швеции',
            'place' => 'Швеция',
            'period' => 'октябрь-ноябрь',
            'starts_month' => 10,
            'people_count' => 8,
            'responsible' => 'Шерзод Султонов',
            'curator' => 'Хаёт Хамраев',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(PressTour::class, [
        'name' => 'Визит СМИ Швеции',
        'direction' => PressTourDirection::Inbound->value,
        'period' => 'октябрь-ноябрь',
        'starts_month' => 10,
    ]);
});

it('requires a name', function () {
    actingAs(userWithPermission('view_any_press_tour', 'create_press_tour'));

    Livewire::test(CreatePressTour::class)
        ->fillForm(['name' => null])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

it('stamps the author on a new tour', function () {
    $user = userWithPermission('view_any_press_tour', 'create_press_tour');
    actingAs($user);

    Livewire::test(CreatePressTour::class)
        ->fillForm(['name' => 'Пресс-тур Бухара'])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(PressTour::class, [
        'name' => 'Пресс-тур Бухара',
        'created_by' => $user->id,
    ]);
});

it('edits a press tour', function () {
    actingAs(userWithPermission('view_any_press_tour', 'view_press_tour', 'update_press_tour'));

    $tour = PressTour::factory()->create(['name' => 'Старое название']);

    Livewire::test(EditPressTour::class, ['record' => $tour->id])
        ->fillForm(['name' => 'Новое название'])
        ->call('save')
        ->assertHasNoFormErrors();

    assertDatabaseHas(PressTour::class, [
        'id' => $tour->id,
        'name' => 'Новое название',
    ]);
});

it('opens a tour on its own page rather than a modal', function () {
    actingAs(userWithPermission('view_any_press_tour', 'view_press_tour'));

    $tour = PressTour::factory()->create(['name' => 'Праздник дыни']);

    expect(PressTourResource::getUrl('view', ['record' => $tour]))->toBeString();

    Livewire::test(ViewPressTour::class, ['record' => $tour->id])
        ->assertSuccessful()
        ->assertSee('Праздник дыни');
});

it('keeps the registry away from a user without the permission', function () {
    actingAs(User::factory()->create(['status' => User::STATUS_ACTIVE]));

    expect(PressTourResource::canViewAny())->toBeFalse();
});
