<?php

use App\Exports\ContactsExport;
use App\Exports\ContactsSheet;
use App\Filament\Resources\Contacts\Pages\ListContacts;
use App\Models\BankAccount;
use App\Models\Contact;
use App\Models\Currency;
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

    expect($legal->headings())
        ->toContain(__('app.label.inn'), __('app.label.mfo').' / SWIFT', __('app.label.director_name'))
        ->not->toContain(__('app.label.pinfl'));

    expect($individual->headings())
        ->toContain(__('app.label.pinfl'))
        ->not->toContain(__('app.label.inn'), __('app.label.director_name'), __('app.label.mfo').' / SWIFT');
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

    expect($row[0])->toBe(1)
        ->and($row[3])->toBe('301234567');
});

it('still emits both sheets when there are no contacts to export', function () {
    $sheets = (new ContactsExport(Contact::query()))->sheets();

    expect($sheets)->toHaveCount(2);
});

it('exports EVERY bank account of a company, prefixed with its currency', function () {
    $legal = Contact::factory()->create(['type' => Contact::TYPE_LEGAL]);
    $usd = Currency::factory()->create(['short_name' => 'USD']);

    BankAccount::factory()->create([
        'contact_id' => $legal->id, 'currency_id' => null, 'sort' => 0,
        'account_number' => '20208000900123456001', 'bank_name' => 'Ипак Йули', 'mfo' => '00444',
    ]);
    BankAccount::factory()->create([
        'contact_id' => $legal->id, 'currency_id' => $usd->id, 'sort' => 1,
        'account_number' => '20208840900123456002', 'bank_name' => 'Ипак Йули', 'mfo' => null, 'swift' => 'INIPUZ22',
    ]);

    $sheet = new ContactsSheet(Contact::query(), Contact::TYPE_LEGAL);
    $row = $sheet->map($sheet->query()->first());

    expect($row[11])->toBe("20208000900123456001\nUSD: 20208840900123456002")
        ->and($row[12])->toBe('Ипак Йули')
        ->and($row[13])->toBe("00444\nUSD: INIPUZ22");
});

it('keeps a single account bare — no currency prefix noise', function () {
    $legal = Contact::factory()->create(['type' => Contact::TYPE_LEGAL]);

    BankAccount::factory()->create([
        'contact_id' => $legal->id, 'currency_id' => null,
        'account_number' => '20208000900123456001', 'bank_name' => 'Капиталбанк', 'mfo' => '01088',
    ]);

    $sheet = new ContactsSheet(Contact::query(), Contact::TYPE_LEGAL);
    $row = $sheet->map($sheet->query()->first());

    expect($row[11])->toBe('20208000900123456001')
        ->and($row[13])->toBe('01088');
});
