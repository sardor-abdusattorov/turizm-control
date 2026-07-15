<?php

use App\Filament\Widgets\Dashboard\ProjectPulseWidget;
use App\Models\Contract;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('defaults to the nearest upcoming active project', function () {
    Project::factory()->international()->create(['name' => 'PAST-EXPO', 'starts_on' => now()->subMonth(), 'status' => true]);
    $next = Project::factory()->international()->create(['name' => 'NEXT-EXPO', 'starts_on' => now()->addWeek(), 'status' => true]);
    Project::factory()->international()->create(['name' => 'LATER-EXPO', 'starts_on' => now()->addMonths(3), 'status' => true]);

    expect(Project::dashboardDefault()?->id)->toBe($next->id);

    actingAs(userWithPermission('view_any_project'));

    Livewire::test(ProjectPulseWidget::class)
        ->assertSee('NEXT-EXPO');
});

it('falls back to the latest past project when nothing is upcoming', function () {
    Project::factory()->international()->create(['name' => 'OLD-EXPO', 'starts_on' => now()->subYear(), 'status' => true]);
    $recent = Project::factory()->international()->create(['name' => 'RECENT-EXPO', 'starts_on' => now()->subMonth(), 'status' => true]);

    expect(Project::dashboardDefault()?->id)->toBe($recent->id);
});

it('shows the project picked in the dashboard filter', function () {
    Project::factory()->international()->create(['name' => 'DEFAULT-EXPO', 'starts_on' => now()->addWeek(), 'status' => true]);
    $picked = Project::factory()->international()->create(['name' => 'PICKED-EXPO', 'starts_on' => now()->addMonths(2), 'status' => true]);

    actingAs(userWithPermission('view_any_project'));

    Livewire::test(ProjectPulseWidget::class, ['pageFilters' => ['project_id' => $picked->id]])
        ->assertSee('PICKED-EXPO')
        ->assertDontSee('DEFAULT-EXPO');
});

it('shows a manager only their own contracts on the pulse widget', function () {
    $project = Project::factory()->international()->create(['name' => 'SCOPED-EXPO', 'starts_on' => now()->addWeek(), 'status' => true]);

    $manager = userWithPermission('view_any_project', 'view_project');

    Contract::factory()->create([
        'project_id' => $project->id,
        'responsible_id' => $manager->id,
        'number' => 'MINE-001',
    ]);
    Contract::factory()->create([
        'project_id' => $project->id,
        'responsible_id' => User::factory()->create()->id,
        'number' => 'FOREIGN-001',
    ]);

    actingAs($manager);

    Livewire::test(ProjectPulseWidget::class, ['pageFilters' => ['project_id' => $project->id]])
        ->assertSee('MINE-001')
        ->assertDontSee('FOREIGN-001');
});

it('is hidden from users without the projects permission', function () {
    actingAs(User::factory()->create(['status' => User::STATUS_ACTIVE]));

    expect(ProjectPulseWidget::canView())->toBeFalse();
});
