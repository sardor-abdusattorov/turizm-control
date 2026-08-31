<?php

use App\Enums\PressTourAttachmentType;
use App\Enums\PressTourDirection;
use App\Enums\PressTourState;
use App\Filament\Resources\PressTours\Pages\CreatePressTour;
use App\Filament\Resources\PressTours\Pages\EditPressTour;
use App\Filament\Resources\PressTours\Pages\ListPressTours;
use App\Filament\Resources\PressTours\Pages\ViewPressTour;
use App\Filament\Resources\PressTours\PressTourResource;
use App\Livewire\AttachmentPanel;
use App\Models\PressTour;
use App\Models\PressTourAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

it('lists press tours for a user holding the permission', function () {
    actingAs(userWithPermission('view_any_press_tour', 'view_press_tour'));

    $tours = PressTour::factory()->count(3)->create();

    Livewire::test(ListPressTours::class)
        ->assertCanSeeTableRecords($tours);
});

it('splits the list into the registry three directions', function () {
    actingAs(userWithPermission('view_any_press_tour', 'view_press_tour'));

    $inbound = PressTour::factory()->inbound()->create();
    $local = PressTour::factory()->create();

    Livewire::test(ListPressTours::class)
        ->set('activeTab', PressTourDirection::Inbound->value)
        ->assertCanSeeTableRecords([$inbound])
        ->assertCanNotSeeTableRecords([$local]);
});

it('filters the list by direction', function () {
    actingAs(userWithPermission('view_any_press_tour', 'view_press_tour'));

    $outbound = PressTour::factory()->outbound()->create();
    $local = PressTour::factory()->create();

    Livewire::test(ListPressTours::class)
        ->filterTable('direction', PressTourDirection::Outbound->value)
        ->assertCanSeeTableRecords([$outbound])
        ->assertCanNotSeeTableRecords([$local]);
});

it('shows the registry headcount wording in the table', function () {
    actingAs(userWithPermission('view_any_press_tour', 'view_press_tour'));

    PressTour::factory()->create([
        'name' => 'Форум СМИ',
        'people_count' => null,
        'people_note' => '6+11',
    ]);

    Livewire::test(ListPressTours::class)->assertSee('6+11');
});

it('creates a press tour', function () {
    actingAs(userWithPermission('view_any_press_tour', 'create_press_tour'));

    Livewire::test(CreatePressTour::class)
        ->fillForm([
            'direction' => PressTourDirection::Inbound->value,
            'name' => 'Визит СМИ Швеции',
            'place' => 'Швеция',
            'period' => 'октябрь-ноябрь',
            'starts_month' => 10,
            'people_count' => 8,
            'responsible' => 'Шерзод Султонов',
            'curator' => 'Хаёт Хамраев',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(PressTour::class, [
        'name' => 'Визит СМИ Швеции',
        'direction' => PressTourDirection::Inbound->value,
        'period' => 'октябрь-ноябрь',
        'starts_month' => 10,
    ]);
});

it('requires a name', function () {
    actingAs(userWithPermission('view_any_press_tour', 'create_press_tour'));

    Livewire::test(CreatePressTour::class)
        ->fillForm(['name' => null])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

it('stamps the author on a new tour', function () {
    $user = userWithPermission('view_any_press_tour', 'create_press_tour');
    actingAs($user);

    Livewire::test(CreatePressTour::class)
        ->fillForm(['name' => 'Пресс-тур Бухара'])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(PressTour::class, [
        'name' => 'Пресс-тур Бухара',
        'created_by' => $user->id,
    ]);
});

it('edits a press tour', function () {
    actingAs(userWithPermission('view_any_press_tour', 'view_press_tour', 'update_press_tour'));

    $tour = PressTour::factory()->create(['name' => 'Старое название']);

    Livewire::test(EditPressTour::class, ['record' => $tour->id])
        ->fillForm(['name' => 'Новое название'])
        ->call('save')
        ->assertHasNoFormErrors();

    assertDatabaseHas(PressTour::class, [
        'id' => $tour->id,
        'name' => 'Новое название',
    ]);
});

it('opens a tour on its own page rather than a modal', function () {
    actingAs(userWithPermission('view_any_press_tour', 'view_press_tour'));

    $tour = PressTour::factory()->create(['name' => 'Праздник дыни']);

    expect(PressTourResource::getUrl('view', ['record' => $tour]))->toBeString();

    Livewire::test(ViewPressTour::class, ['record' => $tour->id])
        ->assertSuccessful()
        ->assertSee('Праздник дыни');
});

it('keeps the registry away from a user without the permission', function () {
    actingAs(User::factory()->create(['status' => User::STATUS_ACTIVE]));

    expect(PressTourResource::canViewAny())->toBeFalse();
});

it('marks a tour as held with its actual date', function () {
    actingAs(userWithPermission('view_any_press_tour', 'view_press_tour', 'update_press_tour'));

    $tour = PressTour::factory()->create();
    expect($tour->state)->toBe(PressTourState::Planned)
        ->and($tour->isHeld())->toBeFalse();

    Livewire::test(EditPressTour::class, ['record' => $tour->id])
        ->fillForm([
            'state' => PressTourState::Held->value,
            'held_on' => '2026-08-20',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($tour->refresh()->isHeld())->toBeTrue()
        ->and($tour->held_on->toDateString())->toBe('2026-08-20');
});

it('demands the actual date once a tour is marked held', function () {
    actingAs(userWithPermission('view_any_press_tour', 'view_press_tour', 'update_press_tour'));

    $tour = PressTour::factory()->create();

    Livewire::test(EditPressTour::class, ['record' => $tour->id])
        ->fillForm([
            'state' => PressTourState::Held->value,
            'held_on' => null,
        ])
        ->call('save')
        ->assertHasFormErrors(['held_on' => 'required']);
});

it('flags a held tour that still owes its report pack', function () {
    $held = PressTour::factory()->held()->create();
    $documented = PressTour::factory()->held()->create();
    PressTourAttachment::factory()->for($documented, 'pressTour')->create();
    $planned = PressTour::factory()->create();

    expect($held->awaitsDocuments())->toBeTrue()
        ->and($documented->awaitsDocuments())->toBeFalse()

        ->and($planned->awaitsDocuments())->toBeFalse();
});

it('filters the list down to held tours missing their documents', function () {
    actingAs(userWithPermission('view_any_press_tour', 'view_press_tour'));

    $awaiting = PressTour::factory()->held()->create();
    $documented = PressTour::factory()->held()->create();
    PressTourAttachment::factory()->for($documented, 'pressTour')->create();
    $planned = PressTour::factory()->create();

    Livewire::test(ListPressTours::class)
        ->filterTable('awaiting_documents')
        ->assertCanSeeTableRecords([$awaiting])
        ->assertCanNotSeeTableRecords([$documented, $planned]);
});

it('drops the report pack when the tour is deleted', function () {
    $tour = PressTour::factory()->held()->create();
    PressTourAttachment::factory()->for($tour, 'pressTour')->create();

    $tour->delete();

    expect(PressTourAttachment::where('press_tour_id', $tour->id)->count())->toBe(0);
});

it('offers the export only to a user holding the export permission', function () {
    actingAs(userWithPermission('view_any_press_tour', 'view_press_tour'));
    Livewire::test(ListPressTours::class)->assertActionHidden('exportXlsx');

    actingAs(userWithPermission('view_any_press_tour', 'view_press_tour', 'export_press_tour'));
    Livewire::test(ListPressTours::class)->assertActionVisible('exportXlsx');
});

it('reads a tour on a designed page, not a disabled form', function () {
    actingAs(userWithPermission('view_any_press_tour', 'view_press_tour'));

    $tour = PressTour::factory()->held()->create([
        'name' => 'Праздник дыни',
        'place' => 'Хорезм',
        'responsible' => 'Хаёт Хамраев',
    ]);

    Livewire::test(ViewPressTour::class, ['record' => $tour->id])
        ->assertSuccessful()

        ->assertSee('Хорезм')
        ->assertSee('Хаёт Хамраев')
        ->assertSeeLivewire(AttachmentPanel::class)

        ->assertSee(__('app.message.press_tour_documents_pending'));
});

it('shows the filed report pack in the documents upload panel', function () {
    Storage::fake('local');

    actingAs(userWithPermission('view_any_press_tour', 'view_press_tour'));

    $tour = PressTour::factory()->held()->create();
    $document = PressTourAttachment::factory()->for($tour, 'pressTour')->create([
        'original_name' => 'Отчёт о пресс-туре.pdf',
    ]);
    Storage::disk('local')->put($document->file_path, 'pdf');

    $panel = Livewire::test(AttachmentPanel::class, ['variant' => 'press-tour-documents', 'recordId' => $tour->id])
        ->assertSuccessful()
        ->assertFormSet(['document_files' => [$document->file_path]]);

    expect($panel->instance()->canManage())->toBeFalse();
});

it('files a report document through the upload panel', function () {
    Storage::fake('local');

    actingAs(userWithPermission('view_any_press_tour', 'view_press_tour', 'update_press_tour'));

    $tour = PressTour::factory()->held()->create();

    Livewire::test(AttachmentPanel::class, ['variant' => 'press-tour-documents', 'recordId' => $tour->id])
        ->fillForm([
            'type' => PressTourAttachmentType::MediaCoverage->value,
            'document_files' => [UploadedFile::fake()->create('Публикации в СМИ.pdf', 40, 'application/pdf')],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $document = $tour->attachments()->sole();

    expect($document->original_name)->toBe('Публикации в СМИ.pdf')
        ->and($document->type)->toBe(PressTourAttachmentType::MediaCoverage);

    Storage::disk('local')->assertExists($document->file_path);
});

it('drops a document whose chip was removed from the panel', function () {
    Storage::fake('local');

    actingAs(userWithPermission('view_any_press_tour', 'view_press_tour', 'update_press_tour'));

    $tour = PressTour::factory()->held()->create();
    $document = PressTourAttachment::factory()->for($tour, 'pressTour')->create();
    Storage::disk('local')->put($document->file_path, 'pdf');

    Livewire::test(AttachmentPanel::class, ['variant' => 'press-tour-documents', 'recordId' => $tour->id])
        ->fillForm(['document_files' => []])
        ->call('save');

    expect($tour->attachments()->count())->toBe(0);
    Storage::disk('local')->assertMissing($document->file_path);
});
