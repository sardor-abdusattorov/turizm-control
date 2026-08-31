<?php

use App\Enums\ProjectType;
use App\Exports\ProjectsRegistryExport;
use App\Exports\SponsorsExport;
use App\Filament\Resources\Projects\Pages\ListInternationalProjects;
use App\Filament\Resources\Sponsors\Pages\ListSponsors;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Project;
use App\Models\Sponsor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('reproduces the registry block format', function () {
    app()->setLocale('ru');

    $eur = Currency::factory()->create(['short_name' => 'EUR']);
    $uzs = Currency::factory()->create(['short_name' => 'UZS']);
    $feeType = ContractType::factory()->income()->create();

    $named = fn (string $name) => Contact::factory()->create(['name' => ['ru' => $name, 'uz' => $name, 'en' => $name]]);
    $rocket = $named('ROCKET DMC');
    $zamin = $named('ZAMIN DMC');
    $beyond = $named('BEYOND DMC');

    $fitur = Project::factory()->international()->create([
        'name' => 'FITUR-2025',
        'starts_on' => '2025-01-22',
        'ends_on' => '2025-01-26',
        'area_sqm' => 76.5,
        'area_cost' => 16595.41,
        'area_is_free' => false,
        'area_currency_id' => $eur->id,
        'stand_cost' => 47890,
        'stand_currency_id' => $eur->id,
    ]);

    $fee = fn (Project $project, Contact $contact, float $amount) => Contract::factory()->create([
        'project_id' => $project->id,
        'contact_id' => $contact->id,
        'contract_type_id' => $feeType->id,
        'currency_id' => $uzs->id,
        'amount' => $amount,
        'status' => Contract::STATUS_APPROVED,
    ]);
    $fee($fitur, $rocket, 38000000);
    $fee($fitur, $zamin, 38000000);

    $atm = Project::factory()->international()->create([
        'name' => 'ATM-2025',
        'starts_on' => '2025-04-28',
        'ends_on' => '2025-05-01',
        'area_sqm' => 66,
        'area_cost' => null,
        'area_is_free' => true,
        'area_currency_id' => null,
        'stand_cost' => 52705.8,
        'stand_currency_id' => $eur->id,
    ]);
    $fee($atm, $beyond, 10000000);

    $rows = (new ProjectsRegistryExport(Project::query(), ProjectType::International))->array();

    expect($rows)->toHaveCount(6)
        ->and($rows[0][1])->toBe('Название выставки')
        ->and($rows[1][0])->toBe(1)
        ->and($rows[1][1])->toBe('FITUR-2025')
        ->and($rows[1][2])->toBe('22-26-января')
        ->and($rows[1][3])->toBe('76,5м2')
        ->and($rows[1][5])->toBe('EURO')
        ->and($rows[1][8])->toBe('ROCKET DMC')
        ->and($rows[1][10])->toBe('Сум')
        ->and($rows[1][11])->toBe(76000000.0)
        ->and($rows[2][1])->toBeNull()
        ->and($rows[2][8])->toBe('ZAMIN DMC')
        ->and($rows[3][0])->toBe('Итог')
        ->and($rows[4][2])->toBe('28-апреля-1-мая')
        ->and($rows[4][3])->toBe('66м2')
        ->and($rows[4][4])->toBe('бесплатно');
});

it('shows the projects export action only to permission holders', function () {
    Permission::findOrCreate('view_any_project', 'web');
    Permission::findOrCreate('export_project', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('view_any_project');
    actingAs($user->fresh());

    Livewire::test(ListInternationalProjects::class)->assertTableActionHidden('exportXlsx');

    $user->givePermissionTo('export_project');
    actingAs($user->fresh());

    Livewire::test(ListInternationalProjects::class)->assertTableActionVisible('exportXlsx');
});

it('exports sponsors with full requisites', function () {
    $sponsor = Sponsor::factory()->create([
        'name' => 'UZBEKISTAN AIRWAYS AJ',
        'inn' => '201234567',
        'contact_person' => 'Иван Иванов',
    ]);

    $export = new SponsorsExport(Sponsor::query());
    $row = $export->map($export->query()->first());

    expect($export->headings())->toHaveCount(11)
        ->and($row[1])->toBe('UZBEKISTAN AIRWAYS AJ')
        ->and($row[2])->toBe('201234567')
        ->and($row[3])->toBe('Иван Иванов');
});

it('shows the sponsors export action only to permission holders', function () {
    Permission::findOrCreate('view_any_sponsor', 'web');
    Permission::findOrCreate('export_sponsor', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('view_any_sponsor');
    actingAs($user->fresh());

    Livewire::test(ListSponsors::class)->assertTableActionHidden('exportXlsx');

    $user->givePermissionTo('export_sponsor');
    actingAs($user->fresh());

    Livewire::test(ListSponsors::class)->assertTableActionVisible('exportXlsx');
});
