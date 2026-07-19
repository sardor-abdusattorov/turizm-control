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
    // filtering to internal CLEARS the selection — filters first, then the
    // user picks; nothing is auto-chosen. The prompt replaces the card.
    Livewire::test(ProjectPulseWidget::class)
        ->assertSet('data.projectId', $intl->id)
        ->assertSee('Берлин')
        ->set('filters.type', 'internal')
        ->assertSet('data.projectId', null)
        ->assertSee(__('app.message.pulse_pick_project'))
        ->assertDontSee('Берлин');

    expect(session('dashboard.project_id'))->toBeNull();
});

it('keeps the selection when it still matches the changed filter', function () {
    $a = Project::factory()->international()->create(['name' => 'EXPO-A', 'starts_on' => now()->addWeek(), 'status' => true, 'venue' => 'Париж']);
    Project::factory()->international()->create(['name' => 'EXPO-B', 'starts_on' => now()->addMonths(2), 'status' => true]);

    actingAs(userWithPermission('view_any_project'));

    Livewire::test(ProjectPulseWidget::class)
        ->assertSet('data.projectId', $a->id)
        ->set('filters.type', 'international')
        ->assertSet('data.projectId', $a->id)
        ->assertSee('Париж');
});

it('clears the selection when the year filter excludes it, arrows pick from the narrowed list', function () {
    $past = Project::factory()->international()->create(['name' => 'EXPO-PAST', 'starts_on' => now()->subYear(), 'status' => true, 'venue' => 'Токио']);
    $upcoming = Project::factory()->international()->create(['name' => 'EXPO-NEXT', 'starts_on' => now()->addWeek(), 'status' => true, 'venue' => 'Дубай']);

    actingAs(userWithPermission('view_any_project'));

    // The old selection no longer matches → cleared; the arrow is an explicit
    // pick, so stepping from the empty state lands on the filtered project.
    Livewire::test(ProjectPulseWidget::class)
        ->assertSet('data.projectId', $upcoming->id)
        ->set('filters.year', (string) now()->subYear()->year)
        ->assertSet('data.projectId', null)
        ->call('stepProject', 1)
        ->assertSet('data.projectId', $past->id)
        ->assertSee('Токио');
});

it('steps through projects with the arrows in dropdown order, wrapping around', function () {
    // pickerQuery orders newest first: C (in 3 months), B (in 2), A (in 1).
    $a = Project::factory()->international()->create(['name' => 'EXPO-A', 'starts_on' => now()->addMonth(), 'status' => true]);
    $b = Project::factory()->international()->create(['name' => 'EXPO-B', 'starts_on' => now()->addMonths(2), 'status' => true]);
    $c = Project::factory()->international()->create(['name' => 'EXPO-C', 'starts_on' => now()->addMonths(3), 'status' => true]);

    actingAs(userWithPermission('view_any_project'));

    $ordered = Project::filteredIds();

    // The default selection is the nearest upcoming project (A, the list's
    // tail); stepping forward wraps to the head, stepping back returns.
    Livewire::test(ProjectPulseWidget::class)
        ->assertSet('data.projectId', $a->id)
        ->call('stepProject', 1)
        ->assertSet('data.projectId', $ordered[0])
        ->call('stepProject', -1)
        ->assertSet('data.projectId', $a->id);

    expect($ordered)->toBe([$c->id, $b->id, $a->id])
        ->and(session('dashboard.project_id'))->toBe($a->id);
});

it('steps only within the filtered project list', function () {
    Project::factory()->internal()->create(['name' => 'LOCAL-FEST', 'starts_on' => now()->addWeek(), 'status' => true]);
    $intl1 = Project::factory()->international()->create(['name' => 'EXPO-1', 'starts_on' => now()->addMonth(), 'status' => true]);
    $intl2 = Project::factory()->international()->create(['name' => 'EXPO-2', 'starts_on' => now()->addMonths(2), 'status' => true]);

    actingAs(userWithPermission('view_any_project'));

    // Narrowing to international clears the (internal) selection; the arrows
    // then cycle through the two matching projects, list head first, and never
    // land on the internal one.
    Livewire::test(ProjectPulseWidget::class)
        ->set('filters.type', 'international')
        ->assertSet('data.projectId', null)
        ->call('stepProject', 1)
        ->assertSet('data.projectId', $intl2->id)
        ->call('stepProject', 1)
        ->assertSet('data.projectId', $intl1->id)
        ->call('stepProject', 1)
        ->assertSet('data.projectId', $intl2->id);
});

it('is hidden from users without the projects permission', function () {
    actingAs(User::factory()->create(['status' => User::STATUS_ACTIVE]));

    expect(ProjectPulseWidget::canView())->toBeFalse();
});
