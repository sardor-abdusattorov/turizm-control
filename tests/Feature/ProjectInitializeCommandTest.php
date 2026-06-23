<?php

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('refuses to run project:init in production so it cannot wipe a live database', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $survivor = User::factory()->create();

    $exit = Artisan::call('project:init');

    expect($exit)->toBe(Command::FAILURE)
        ->and(User::whereKey($survivor->id)->exists())->toBeTrue();
});
