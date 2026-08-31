<?php

use App\Filament\Pages\Settings;
use App\Models\Settings as SettingsModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('mounts the settings page and resolves its form via schema auto-discovery', function () {
    actingAs(userWithPermission('view_settings'));

    Livewire::test(Settings::class)
        ->assertOk()
        ->assertFormExists('form');
});

it('serves every setting from a single cached read', function () {
    SettingsModel::set('approval.sla_days', 4);
    SettingsModel::get('approval.sla_days');

    DB::enableQueryLog();
    SettingsModel::get('approval.sla_days');
    SettingsModel::get('approval.flow');
    settings('nothing.here', 'fallback');
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBeEmpty()
        ->and(settings('nothing.here', 'fallback'))->toBe('fallback');
});

it('busts the cache when a setting is written or cleared', function () {
    expect(settings('approval.sla_days', 2))->toBe(2);

    SettingsModel::set('approval.sla_days', 7);

    expect(settings('approval.sla_days', 2))->toBe(7);

    SettingsModel::query()->where('key', 'approval.sla_days')->update(['value' => '9']);
    clear_settings_cache();

    expect(settings('approval.sla_days', 2))->toBe(9);
});
