<?php

use App\Enums\ParticipantRole;
use App\Enums\ProjectType;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use App\Models\ProjectParticipant;
use App\Models\ProjectPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

it('forbids the project list without permission', function () {
    actingAs(userWithPermission('view_profile_settings'));

    Livewire::test(ListProjects::class)->assertForbidden();
});

it('lists projects with type tabs', function () {
    Project::factory()->internal()->create(['name' => 'INTERNAL-EXPO-2025']);
    Project::factory()->international()->create(['name' => 'WORLD-EXPO-2025']);

    actingAs(userWithPermission('view_any_project'));

    Livewire::test(ListProjects::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords(Project::all())
        ->set('activeTab', ProjectType::International->value)
        ->assertCanSeeTableRecords(Project::where('type', ProjectType::International)->get())
        ->assertCanNotSeeTableRecords(Project::where('type', ProjectType::Internal)->get());
});

it('opens the list pre-filtered from the sidebar tab query parameter', function () {
    $internal = Project::factory()->internal()->create(['name' => 'INTERNAL-EXPO-2025']);
    $international = Project::factory()->international()->create(['name' => 'WORLD-EXPO-2025']);

    actingAs(userWithPermission('view_any_project'));

    // ListRecords binds $activeTab to `?tab=` — the sidebar items rely on it.
    Livewire::withQueryParams(['tab' => ProjectType::Internal->value])
        ->test(ListProjects::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords(collect([$internal]))
        ->assertCanNotSeeTableRecords(collect([$international]));

    $urls = array_map(
        fn ($item): string => $item->getUrl(),
        ProjectResource::getNavigationItems(),
    );

    expect($urls[0])->toContain('tab=internal')
        ->and($urls[1])->toContain('tab=international');
});

it('creates a project with participants and stamps the author', function () {
    $user = userWithPermission('view_any_project', 'create_project');
    actingAs($user);

    Livewire::test(CreateProject::class)
        ->fillForm([
            'type' => ProjectType::International->value,
            'name' => 'FITUR-2026',
            'starts_on' => '2026-01-21',
            'ends_on' => '2026-01-25',
            'area_sqm' => 80,
            'area_cost' => 17000,
            'stand_cost' => 50000,
            'participants' => [
                ['role' => ParticipantRole::Participant->value, 'name' => 'OOO "ZAMIN DMC"', 'amount' => 40000000],
                ['role' => ParticipantRole::Participant->value, 'name' => 'ORIENT STAR GROUP MCHJ', 'amount' => 35000000],
                ['role' => ParticipantRole::Sponsor->value, 'name' => 'UZBEKISTAN AIRWAYS AJ', 'amount' => 100000000],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $project = Project::where('name', 'FITUR-2026')->firstOrFail();

    expect($project->type)->toBe(ProjectType::International)
        ->and($project->created_by)->toBe($user->id)
        ->and($project->participants)->toHaveCount(3)
        ->and($project->sponsors)->toHaveCount(1)
        ->and($project->sponsors->first()->name)->toBe('UZBEKISTAN AIRWAYS AJ')
        ->and($project->feesTotal())->toBe(175000000.0);
});

it('validates that the project name and type are required', function () {
    actingAs(userWithPermission('view_any_project', 'create_project'));

    Livewire::test(CreateProject::class)
        ->fillForm([
            'type' => null,
            'name' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['type', 'name']);
});

it('updates a project', function () {
    $project = Project::factory()->internal()->create(['name' => 'OLD-NAME']);

    actingAs(userWithPermission('view_any_project', 'view_project', 'update_project'));

    Livewire::test(EditProject::class, ['record' => $project->id])
        ->fillForm(['name' => 'NEW-NAME'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($project->fresh()->name)->toBe('NEW-NAME');
});

it('deletes participant rows together with the project', function () {
    $project = Project::factory()->create();
    ProjectParticipant::factory()->count(3)->create(['project_id' => $project->id]);

    $project->delete();

    expect(ProjectParticipant::count())->toBe(0);
});

it('renders the gallery through the image-gallery component', function () {
    Storage::disk('local')->put('uploads/images/projects/2025/01/a.jpg', 'stub');

    $project = Project::factory()->create([
        'gallery' => ['uploads/images/projects/2025/01/a.jpg'],
    ]);

    actingAs(userWithPermission('view_any_project', 'view_project'));

    Livewire::test(ViewProject::class, ['record' => $project->id])
        ->assertSuccessful()
        ->assertSee('data-viewer-gallery', false);
});

it('records a project payment through the view page action', function () {
    $project = Project::factory()->create();
    $participant = ProjectParticipant::factory()->create([
        'project_id' => $project->id,
        'amount' => 25_000_000,
        'paid_amount' => 0,
    ]);

    actingAs(userWithPermission('view_any_project', 'view_project', 'record_project_payment'));

    Livewire::test(ViewProject::class, ['record' => $project->id])
        ->callAction('recordPayment', [
            'project_participant_id' => $participant->id,
            'amount' => 25_000_000,
            'paid_at' => now()->toDateString(),
            'screenshot' => UploadedFile::fake()->image('proof.jpg'),
        ])
        ->assertHasNoActionErrors();

    expect((float) $participant->fresh()->paid_amount)->toBe(25_000_000.0)
        ->and(ProjectPayment::where('project_participant_id', $participant->id)->count())->toBe(1);
});
