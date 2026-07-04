<?php

use App\Enums\ParticipantRole;
use App\Enums\ProjectType;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Models\Project;
use App\Models\ProjectParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
