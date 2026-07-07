<?php

use App\Filament\Widgets\Dashboard\FeaturedProjectWidget;
use App\Models\Project;
use App\Models\ProjectParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('features the running project over an upcoming one', function () {
    Project::factory()->international()->create([
        'name' => 'FUTURE-EXPO',
        'starts_on' => today()->addMonths(2),
        'ends_on' => today()->addMonths(2)->addDays(3),
    ]);
    $running = Project::factory()->international()->create([
        'name' => 'RUNNING-EXPO',
        'starts_on' => today()->subDay(),
        'ends_on' => today()->addDays(2),
    ]);
    ProjectParticipant::factory()->create([
        'project_id' => $running->id,
        'amount' => 40_000_000,
        'paid_amount' => 10_000_000,
    ]);

    actingAs(userWithPermission('view_featured_project_widget'));

    Livewire::test(FeaturedProjectWidget::class)
        ->assertSee('RUNNING-EXPO')
        ->assertSee('40 000 000');
});

it('falls back to the nearest upcoming project', function () {
    Project::factory()->internal()->create([
        'name' => 'NEXT-EVENT',
        'starts_on' => today()->addDays(10),
        'ends_on' => today()->addDays(12),
    ]);

    actingAs(userWithPermission('view_featured_project_widget'));

    Livewire::test(FeaturedProjectWidget::class)->assertSee('NEXT-EVENT');
});
