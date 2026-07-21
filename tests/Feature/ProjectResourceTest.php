<?php

use App\Enums\ProjectType;
use App\Filament\Resources\Projects\InternalProjectResource;
use App\Filament\Resources\Projects\InternationalProjectResource;
use App\Filament\Resources\Projects\Pages\CreateInternationalProject;
use App\Filament\Resources\Projects\Pages\EditInternalProject;
use App\Filament\Resources\Projects\Pages\ListInternalProjects;
use App\Filament\Resources\Projects\Pages\ListInternationalProjects;
use App\Filament\Resources\Projects\Pages\ViewInternationalProject;
use App\Livewire\MediaLibrary;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Project;
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

it('creates a project stamping the resource type and author', function () {
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
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $project = Project::where('name', 'FITUR-2026')->firstOrFail();

    expect($project->type)->toBe(ProjectType::International)
        ->and($project->created_by)->toBe($user->id);
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

it('renders the gallery as a native Filament FileUpload on the view page', function () {
    Storage::disk('local')->put('uploads/images/projects/2025/01/a.jpg', 'stub');

    $project = Project::factory()->international()->create([
        'gallery' => ['uploads/images/projects/2025/01/a.jpg'],
    ]);

    actingAs(userWithPermission('view_any_project', 'view_project'));

    Livewire::test(ViewInternationalProject::class, ['record' => $project->id])
        ->assertSuccessful()
        ->assertSeeLivewire(MediaLibrary::class);
});

it('uploads gallery files inline through the media library, appending to the set', function () {
    Storage::disk('local')->put('uploads/images/projects/2025/01/old.jpg', 'stub');

    $project = Project::factory()->international()->create([
        'gallery' => ['uploads/images/projects/2025/01/old.jpg'],
    ]);

    actingAs(userWithPermission('view_any_project', 'view_project', 'update_project'));

    Livewire::test(MediaLibrary::class, ['variant' => 'project-gallery', 'recordId' => $project->id])
        ->assertSet('canEdit', true)
        ->fillForm([
            'gallery' => [
                'uploads/images/projects/2025/01/old.jpg',
                UploadedFile::fake()->image('new.jpg'),
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $gallery = $project->fresh()->gallery;

    // The old file survives, the new upload lands after it.
    expect($gallery)->toHaveCount(2)
        ->and($gallery[0])->toBe('uploads/images/projects/2025/01/old.jpg');
});

it('disables inline gallery editing for users without update_project', function () {
    Storage::disk('local')->put('uploads/images/projects/2025/01/a.jpg', 'stub');

    $project = Project::factory()->international()->create([
        'gallery' => ['uploads/images/projects/2025/01/a.jpg'],
    ]);

    actingAs(userWithPermission('view_any_project', 'view_project'));

    Livewire::test(MediaLibrary::class, ['variant' => 'project-gallery', 'recordId' => $project->id])
        ->assertSet('canEdit', false)
        // A save attempt from a viewer is a no-op — the set is untouched.
        ->call('save');

    expect($project->fresh()->gallery)->toBe(['uploads/images/projects/2025/01/a.jpg']);
});

it('splits gallery urls into images and videos', function () {
    Storage::disk('local')->put('uploads/images/projects/2025/01/a.jpg', 'stub');
    Storage::disk('local')->put('uploads/images/projects/2025/01/b.mp4', 'stub');

    $project = Project::factory()->international()->create([
        'gallery' => [
            'uploads/images/projects/2025/01/a.jpg',
            'uploads/images/projects/2025/01/b.mp4',
        ],
    ]);

    expect($project->galleryImageUrls())->toHaveCount(1)
        ->and($project->galleryImageUrls()[0])->toContain('a.jpg')
        ->and($project->galleryVideoUrls())->toHaveCount(1)
        ->and($project->galleryVideoUrls()[0])->toContain('b.mp4');
});

it('keeps video files through the inline media library', function () {
    Storage::disk('local')->put('uploads/images/projects/2025/01/clip.mp4', 'stub');

    $project = Project::factory()->international()->create([
        'gallery' => ['uploads/images/projects/2025/01/clip.mp4'],
    ]);

    actingAs(userWithPermission('view_any_project', 'view_project', 'update_project'));

    Livewire::test(MediaLibrary::class, ['variant' => 'project-gallery', 'recordId' => $project->id])
        ->fillForm(['gallery' => ['uploads/images/projects/2025/01/clip.mp4']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($project->fresh()->gallery)->toBe(['uploads/images/projects/2025/01/clip.mp4']);
});

it('opens the role-scoped breakdown modals from the count badges', function () {
    $project = Project::factory()->international()->create(['name' => 'PREVIEW-EXPO-2025']);
    $currency = Currency::factory()->create();

    Contract::factory()->create([
        'contract_type_id' => ContractType::factory()->income(),
        'contact_id' => Contact::factory(),
        'project_id' => $project->id,
        'currency_id' => $currency->id,
        'amount' => 12_000_000,
        'paid_percent' => 0,
        'status' => Contract::STATUS_APPROVED,
    ]);
    Contract::factory()->sponsorship()->create([
        'project_id' => $project->id,
        'currency_id' => $currency->id,
        'amount' => 100_000_000,
        'paid_percent' => 0,
        'status' => Contract::STATUS_APPROVED,
    ]);

    actingAs(userWithPermission('view_any_project', 'view_all_contracts'));

    Livewire::test(ListInternationalProjects::class)
        ->assertSuccessful()
        ->mountAction(TestAction::make('participantsBreakdown')->table($project))
        ->assertSuccessful()
        ->unmountAction()
        ->mountAction(TestAction::make('sponsorsBreakdown')->table($project))
        ->assertSuccessful();
});

it('scopes the project contracts badge totals to what the viewer may see', function () {
    $project = Project::factory()->international()->create();

    $manager = userWithPermission('view_any_project', 'view_project');

    Contract::factory()->create([
        'project_id' => $project->id,
        'responsible_id' => $manager->id,
        'amount' => 1000,
    ]);
    Contract::factory()->create([
        'project_id' => $project->id,
        'responsible_id' => User::factory()->create()->id,
        'amount' => 999_999,
    ]);

    actingAs($manager);

    // The manager only counts their own contract; the totals never leak the
    // other manager's amount.
    $totals = $project->visibleContractTotalsByCurrency();

    expect($project->visibleContracts())->toHaveCount(1)
        ->and($totals->sum('count'))->toBe(1)
        ->and($totals->sum('total'))->toBe(1000.0);
});

it('scopes the breakdown view to one role and totals it per currency', function () {
    $uzs = Currency::factory()->create(['short_name' => 'UZS']);
    $project = Project::factory()->international()->create();

    Contract::factory()->create([
        'contract_type_id' => ContractType::factory()->income(),
        'contact_id' => Contact::factory(),
        'project_id' => $project->id,
        'currency_id' => $uzs->id,
        'amount' => 12_000_000,
        'paid_percent' => 100,
        'status' => Contract::STATUS_APPROVED,
    ]);
    Contract::factory()->sponsorship()->create([
        'project_id' => $project->id,
        'currency_id' => $uzs->id,
        'amount' => 100_000_000,
        'paid_percent' => 0,
        'status' => Contract::STATUS_APPROVED,
    ]);

    actingAs(userWithPermission('view_any_project', 'view_all_contracts'));

    // The sponsors breakdown must not leak participant fees — and vice versa:
    // each role totals its own income contracts, per currency.
    $sponsorTotals = $project->incomeTotalsByCurrency(true);
    $feeTotals = $project->incomeTotalsByCurrency(false);

    expect($sponsorTotals)->toHaveCount(1)
        ->and($sponsorTotals->first())->toMatchArray(['currency' => 'UZS', 'count' => 1, 'total' => 100_000_000.0, 'paid' => 0.0])
        ->and($feeTotals)->toHaveCount(1)
        ->and($feeTotals->first())->toMatchArray(['currency' => 'UZS', 'count' => 1, 'total' => 12_000_000.0, 'paid' => 12_000_000.0]);
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
