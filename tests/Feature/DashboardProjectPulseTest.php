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
    $next = Project::factory()->international()->create(['name' => 'NEXT-EXPO', 'starts_on' => now()->addWeek(), 'status' => true, 'venue' => 'Мадрид']);
    Project::factory()->international()->create(['name' => 'LATER-EXPO', 'starts_on' => now()->addMonths(3), 'status' => true]);

    expect(Project::dashboardDefault()?->id)->toBe($next->id);

    actingAs(userWithPermission('view_any_project'));

    Livewire::test(ProjectPulseWidget::class)
        ->assertSet('data.projectId', $next->id)
        ->assertSee('Мадрид');
});

it('falls back to the latest past project when nothing is upcoming', function () {
    Project::factory()->international()->create(['name' => 'OLD-EXPO', 'starts_on' => now()->subYear(), 'status' => true]);
    $recent = Project::factory()->international()->create(['name' => 'RECENT-EXPO', 'starts_on' => now()->subMonth(), 'status' => true]);

    expect(Project::dashboardDefault()?->id)->toBe($recent->id);
});

it('switches the card to the project picked in the toolbar select', function () {
    // Every project name sits in the select options, so the card body is
    // asserted through the venue — it renders only for the shown project.
    Project::factory()->international()->create(['name' => 'DEFAULT-EXPO', 'starts_on' => now()->addWeek(), 'status' => true, 'venue' => 'Барселона']);
    $picked = Project::factory()->international()->create(['name' => 'PICKED-EXPO', 'starts_on' => now()->addMonths(2), 'status' => true, 'venue' => 'Шанхай']);

    actingAs(userWithPermission('view_any_project'));

    Livewire::test(ProjectPulseWidget::class)
        ->assertSee('Барселона')
        ->set('data.projectId', $picked->id)
        ->assertSee('Шанхай')
        ->assertDontSee('Барселона');

    expect(session('dashboard.project_id'))->toBe($picked->id);
});

it('remembers the picked project across dashboard visits', function () {
    Project::factory()->international()->create(['name' => 'DEFAULT-EXPO', 'starts_on' => now()->addWeek(), 'status' => true]);
    $remembered = Project::factory()->international()->create(['name' => 'SAVED-EXPO', 'starts_on' => now()->addMonths(2), 'status' => true]);

    actingAs(userWithPermission('view_any_project'));

    session()->put('dashboard.project_id', $remembered->id);

    Livewire::test(ProjectPulseWidget::class)
        ->assertSet('data.projectId', $remembered->id);
});

it('ignores a remembered project that no longer exists', function () {
    $default = Project::factory()->international()->create(['name' => 'DEFAULT-EXPO', 'starts_on' => now()->addWeek(), 'status' => true]);

    actingAs(userWithPermission('view_any_project'));

    session()->put('dashboard.project_id', 999_999);

    Livewire::test(ProjectPulseWidget::class)
        ->assertSet('data.projectId', $default->id);
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

    Livewire::test(ProjectPulseWidget::class)
        ->set('data.projectId', $project->id)
        ->assertSee('MINE-001')
        ->assertDontSee('FOREIGN-001');
});

it('narrows and rehomes the selection when the type filter changes', function () {
    $intl = Project::factory()->international()->create(['name' => 'GLOBAL-EXPO', 'starts_on' => now()->addWeek(), 'status' => true, 'venue' => 'Берлин']);
    $internal = Project::factory()->internal()->create(['name' => 'LOCAL-FEST', 'starts_on' => now()->addMonths(2), 'status' => true, 'venue' => 'Самарканд']);

    actingAs(userWithPermission('view_any_project'));

    // Default lands on the nearest upcoming project (the international one);
    // filtering to internal jumps the card to the only matching project.
    Livewire::test(ProjectPulseWidget::class)
        ->assertSet('data.projectId', $intl->id)
        ->assertSee('Берлин')
        ->set('data.type', 'internal')
        ->assertSet('data.projectId', $internal->id)
        ->assertSee('Самарканд')
        ->assertDontSee('Берлин');

    expect(session('dashboard.project_id'))->toBe($internal->id);
});

it('keeps the selection when it still matches the changed filter', function () {
    $a = Project::factory()->international()->create(['name' => 'EXPO-A', 'starts_on' => now()->addWeek(), 'status' => true, 'venue' => 'Париж']);
    Project::factory()->international()->create(['name' => 'EXPO-B', 'starts_on' => now()->addMonths(2), 'status' => true]);

    actingAs(userWithPermission('view_any_project'));

    Livewire::test(ProjectPulseWidget::class)
        ->assertSet('data.projectId', $a->id)
        ->set('data.type', 'international')
        ->assertSet('data.projectId', $a->id)
        ->assertSee('Париж');
});

it('rehomes the selection when the year filter changes', function () {
    $past = Project::factory()->international()->create(['name' => 'EXPO-PAST', 'starts_on' => now()->subYear(), 'status' => true, 'venue' => 'Токио']);
    $upcoming = Project::factory()->international()->create(['name' => 'EXPO-NEXT', 'starts_on' => now()->addWeek(), 'status' => true, 'venue' => 'Дубай']);

    actingAs(userWithPermission('view_any_project'));

    Livewire::test(ProjectPulseWidget::class)
        ->assertSet('data.projectId', $upcoming->id)
        ->set('data.year', (string) now()->subYear()->year)
        ->assertSet('data.projectId', $past->id)
        ->assertSee('Токио');
});

it('is hidden from users without the projects permission', function () {
    actingAs(User::factory()->create(['status' => User::STATUS_ACTIVE]));

    expect(ProjectPulseWidget::canView())->toBeFalse();
});
