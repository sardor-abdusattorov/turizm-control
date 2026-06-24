<?php

use App\Filament\Pages\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('mounts the settings page and resolves its form via schema auto-discovery', function () {
    actingAs(userWithPermission('view_settings'));

    Livewire::test(Settings::class)
        ->assertOk()
        ->assertFormExists('form');
});
