<?php

use App\Enums\ParticipantRole;
use App\Enums\ProjectType;
use App\Filament\Resources\Projects\InternalProjectResource;
use App\Filament\Resources\Projects\InternationalProjectResource;
use App\Filament\Resources\Projects\Pages\CreateInternationalProject;
use App\Filament\Resources\Projects\Pages\EditInternalProject;
use App\Filament\Resources\Projects\Pages\ListInternalProjects;
use App\Filament\Resources\Projects\Pages\ListInternationalProjects;
use App\Filament\Resources\Projects\Pages\ViewInternationalProject;
use App\Models\Contract;
use App\Models\Project;
use App\Models\ProjectParticipant;
use App\Models\ProjectPayment;
use App\Models\Sponsor;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

it('forbids the project lists without permission', function () {
    actingAs(userWithPermission('view_profile_settings'));

    Livewire::test(ListInternationalProjects::class)->assertForbidden();
    Livewire::test(ListInternalProjects::class)->assertForbidden();
});

it('scopes each resource to its own project type', function () {
    $internal = Project::factory()->internal()->create(['name' => 'INTERNAL-EXPO-2025']);
    $international = Project::factory()->international()->create(['name' => 'WORLD-EXPO-2025']);

    actingAs(userWithPermission('view_any_project'));

    Livewire::test(ListInternationalProjects::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords(collect([$international]))
        ->assertCanNotSeeTableRecords(collect([$internal]));

    Livewire::test(ListInternalProjects::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords(collect([$internal]))
        ->assertCanNotSeeTableRecords(collect([$international]));

    expect(InternalProjectResource::getUrl('index'))->toContain('internal-projects')
        ->and(InternationalProjectResource::getUrl('index'))->toContain('international-projects');
});

it('creates a project stamping the resource type, author, participants and sponsor', function () {
    $sponsor = Sponsor::factory()->create(['name' => 'UZBEKISTAN AIRWAYS AJ']);
    $user = userWithPermission('view_any_project', 'create_project');
    actingAs($user);

    Livewire::test(CreateInternationalProject::class)
        ->fillForm([
            'name' => 'FITUR-2026',
            'starts_on' => '2026-01-21',
            'ends_on' => '2026-01-25',
            'area_sqm' => 80,
            'area_cost' => 17000,
            'stand_cost' => 50000,
            'participants' => [
                ['role' => ParticipantRole::Participant->value, 'name' => 'OOO "ZAMIN DMC"', 'amount' => 40000000],
                ['role' => ParticipantRole::Sponsor->value, 'sponsor_id' => $sponsor->id, 'name' => $sponsor->name, 'amount' => 100000000],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $project = Project::where('name', 'FITUR-2026')->firstOrFail();

    expect($project->type)->toBe(ProjectType::International)
        ->and($project->created_by)->toBe($user->id)
        ->and($project->participants)->toHaveCount(2)
        ->and($project->sponsors)->toHaveCount(1)
        ->and($project->sponsors->first()->sponsor_id)->toBe($sponsor->id)
        ->and($project->feesTotal())->toBe(140000000.0);
});

it('validates that the project name is required', function () {
    actingAs(userWithPermission('view_any_project', 'create_project'));

    Livewire::test(CreateInternationalProject::class)
        ->fillForm(['name' => null])
        ->call('create')
        ->assertHasFormErrors(['name']);
});

it('updates a project through its typed resource', function () {
    $project = Project::factory()->internal()->create(['name' => 'OLD-NAME']);

    actingAs(userWithPermission('view_any_project', 'view_project', 'update_project'));

    Livewire::test(EditInternalProject::class, ['record' => $project->id])
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

    $project = Project::factory()->international()->create([
        'gallery' => ['uploads/images/projects/2025/01/a.jpg'],
    ]);

    actingAs(userWithPermission('view_any_project', 'view_project'));

    Livewire::test(ViewInternationalProject::class, ['record' => $project->id])
        ->assertSuccessful()
        ->assertSee('data-viewer-gallery', false);
});

it('opens the quick-view modal from the list', function () {
    $project = Project::factory()->international()->create(['name' => 'PREVIEW-EXPO-2025']);
    $participant = ProjectParticipant::factory()->create([
        'project_id' => $project->id,
        'name' => 'OOO "PREVIEW TRAVEL"',
        'amount' => 12_000_000,
    ]);

    actingAs(userWithPermission('view_any_project'));

    Livewire::test(ListInternationalProjects::class)
        ->assertSuccessful()
        ->mountAction(TestAction::make('projectPreview')->table($project))
        ->assertSuccessful();

    $html = view('filament.resources.projects.tables.preview-modal', ['project' => $project])->render();

    expect($html)->toContain('OOO &quot;PREVIEW TRAVEL&quot;')
        ->toContain('12 000 000');
});

it('filters the project list by year', function () {
    $expo2025 = Project::factory()->international()->create(['name' => 'EXPO-2025', 'starts_on' => '2025-03-10', 'ends_on' => '2025-03-12']);
    $expo2026 = Project::factory()->international()->create(['name' => 'EXPO-2026', 'starts_on' => '2026-01-21', 'ends_on' => '2026-01-25']);

    actingAs(userWithPermission('view_any_project'));

    Livewire::test(ListInternationalProjects::class)
        ->assertSuccessful()
        ->filterTable('year', 2026)
        ->assertCanSeeTableRecords(collect([$expo2026]))
        ->assertCanNotSeeTableRecords(collect([$expo2025]));
});

it('shows a manager only their own contracts on the project page', function () {
    $project = Project::factory()->international()->create();

    $manager = userWithPermission('view_any_project', 'view_project');

    Contract::factory()->create([
        'project_id' => $project->id,
        'responsible_id' => $manager->id,
        'number' => 'OWN-2026-001',
    ]);
    Contract::factory()->inReview()->create([
        'project_id' => $project->id,
        'responsible_id' => User::factory()->create()->id,
        'number' => 'FOREIGN-2026-002',
    ]);

    actingAs($manager);

    Livewire::test(ViewInternationalProject::class, ['record' => $project->id])
        ->assertSuccessful()
        ->assertSee('OWN-2026-001')
        ->assertDontSee('FOREIGN-2026-002');
});

it('records a project payment through the view page action', function () {
    $project = Project::factory()->international()->create();
    $participant = ProjectParticipant::factory()->create([
        'project_id' => $project->id,
        'amount' => 25_000_000,
        'paid_amount' => 0,
    ]);

    actingAs(userWithPermission('view_any_project', 'view_project', 'record_project_payment'));

    Livewire::test(ViewInternationalProject::class, ['record' => $project->id])
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
