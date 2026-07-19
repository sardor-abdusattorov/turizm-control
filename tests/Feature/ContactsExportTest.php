<?php

use App\Exports\ContactsExport;
use App\Exports\ContactsSheet;
use App\Filament\Resources\Contacts\Pages\ListContacts;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function actAsContactExporter(): void
{
    Permission::findOrCreate('view_any_contact', 'web');
    Permission::findOrCreate('export_contact', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('view_any_contact', 'export_contact');
    actingAs($user->fresh());
}

it('disables the export button while the filtered table has nothing to export', function () {
    actAsContactExporter();

    Livewire::test(ListContacts::class)->assertTableActionDisabled('exportXlsx');

    Contact::factory()->create(['type' => Contact::TYPE_LEGAL]);

    Livewire::test(ListContacts::class)
        ->assertTableActionEnabled('exportXlsx')
        // An active filter that matches nothing greys the button out again.
        ->filterTable('type', Contact::TYPE_INDIVIDUAL)
        ->assertTableActionDisabled('exportXlsx');
});

it('exports only the rows the active filter leaves on screen', function () {
    actAsContactExporter();
    seedMixedContacts();

    Excel::fake();

    Livewire::test(ListContacts::class)
        ->filterTable('type', Contact::TYPE_LEGAL)
        ->callTableAction('exportXlsx');

    Excel::assertDownloaded(
        'contacts-'.now()->format('Y-m-d').'.xlsx',
        function (ContactsExport $export): bool {
            $rows = collect($export->sheets())
                ->flatMap(fn (ContactsSheet $sheet) => $sheet->query()->get());

            return $rows->count() === 1
                && $rows->first()->type === Contact::TYPE_LEGAL;
        },
    );
});

it('shows the contacts export action only to permission holders', function () {
    Permission::findOrCreate('view_any_contact', 'web');
    Permission::findOrCreate('export_contact', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('view_any_contact');
    actingAs($user->fresh());

    Livewire::test(ListContacts::class)->assertTableActionHidden('exportXlsx');

    $user->givePermissionTo('export_contact');
    actingAs($user->fresh());

    Livewire::test(ListContacts::class)->assertTableActionVisible('exportXlsx');
});

function seedMixedContacts(): void
{
    Contact::factory()->create(['type' => Contact::TYPE_LEGAL, 'inn' => '301234567', 'pinfl' => null]);
    Contact::factory()->create(['type' => Contact::TYPE_INDIVIDUAL, 'inn' => null, 'pinfl' => '30101200012345']);
}

it('splits contacts into a legal and an individual sheet', function () {
    seedMixedContacts();

    $sheets = (new ContactsExport(Contact::query()))->sheets();

    expect($sheets)->toHaveCount(2)
        ->and($sheets[0]->title())->toBe(__('app.export.contacts_legal_sheet'))
        ->and($sheets[1]->title())->toBe(__('app.export.contacts_individual_sheet'));
});

it('keeps individual-only fields out of the legal sheet and vice versa', function () {
    seedMixedContacts();

    [$legal, $individual] = (new ContactsExport(Contact::query()))->sheets();

    // Legal sheet carries requisites but never the PINFL.
    expect($legal->headings())
        ->toContain(__('app.label.inn'), __('app.label.mfo'), __('app.label.director_name'))
        ->not->toContain(__('app.label.pinfl'));

    // Individual sheet carries PINFL but none of the legal-only columns.
    expect($individual->headings())
        ->toContain(__('app.label.pinfl'))
        ->not->toContain(__('app.label.inn'), __('app.label.director_name'), __('app.label.mfo'));
});

it('emits only the legal sheet when the query is filtered to legal entities', function () {
    seedMixedContacts();

    $sheets = (new ContactsExport(Contact::query()->where('type', Contact::TYPE_LEGAL)))->sheets();

    expect($sheets)->toHaveCount(1)
        ->and($sheets[0]->query()->get())->toHaveCount(1)
        ->and($sheets[0]->query()->first()->type)->toBe(Contact::TYPE_LEGAL);
});

it('numbers rows sequentially per sheet and routes the type value to its column', function () {
    $legal = Contact::factory()->create([
        'type' => Contact::TYPE_LEGAL,
        'inn' => '301234567',
    ]);

    $sheet = new ContactsSheet(Contact::query(), Contact::TYPE_LEGAL);
    $row = $sheet->map($legal->fresh());

    expect($row[0])->toBe(1)             // sequential №, not the model id
        ->and($row[3])->toBe('301234567'); // INN sits in its own column
});

it('still emits both sheets when there are no contacts to export', function () {
    $sheets = (new ContactsExport(Contact::query()))->sheets();

    expect($sheets)->toHaveCount(2);
});
