<?php

use App\Models\User;
use BezhanSalleh\LanguageSwitch\Events\LocaleChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('persists the picked panel language to the profile', function () {
    $user = User::factory()->create(['locale' => null]);

    actingAs($user);
    event(new LocaleChanged('uz'));

    expect($user->fresh()->locale)->toBe('uz');
});

it('overwrites a previously saved language on a new switch', function () {
    $user = User::factory()->create(['locale' => 'uz']);

    actingAs($user);
    event(new LocaleChanged('ru'));

    expect($user->fresh()->locale)->toBe('ru');
});

it('ignores the event for guests', function () {
    // The switch is also visible on the login page (outsidePanels) — a guest
    // click must not crash the listener.
    event(new LocaleChanged('en'));

    expect(true)->toBeTrue();
});
