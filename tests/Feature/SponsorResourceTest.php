<?php

use App\Filament\Resources\Sponsors\Pages\CreateSponsor;
use App\Filament\Resources\Sponsors\Pages\ListSponsors;
use App\Models\Sponsor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('forbids the sponsor list without permission', function () {
    actingAs(userWithPermission('view_profile_settings'));

    Livewire::test(ListSponsors::class)->assertForbidden();
});

it('lists sponsors with permission', function () {
    $sponsors = Sponsor::factory()->count(2)->create();

    actingAs(userWithPermission('view_any_sponsor'));

    Livewire::test(ListSponsors::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($sponsors);
});

it('creates a sponsor', function () {
    actingAs(userWithPermission('view_any_sponsor', 'create_sponsor'));

    Livewire::test(CreateSponsor::class)
        ->fillForm([
            'name' => 'UZBEKISTAN AIRWAYS AJ',
            'website' => 'https://uzairways.com',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Sponsor::where('name', 'UZBEKISTAN AIRWAYS AJ')->exists())->toBeTrue();
});
