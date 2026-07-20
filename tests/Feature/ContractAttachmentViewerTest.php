<?php

use App\Filament\Resources\Contracts\Widgets\ContractAttachmentsTableWidget;
use App\Models\Contract;
use App\Models\ContractAttachment;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function dossierViewer(Contract $contract): User
{
    $user = $contract->responsible;

    foreach (['view_any_contract', 'view_contract'] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }

    actingAs($user->fresh());

    return $user;
}

function dossierFile(Contract $contract, string $name, string $storedAs): ContractAttachment
{
    Storage::disk('local')->put('uploads/files/contract-attachments/t/'.$storedAs, 'file-bytes');

    return $contract->attachments()->create([
        'file_path' => 'uploads/files/contract-attachments/t/'.$storedAs,
        'original_name' => $name,
        'size' => 10,
        'uploaded_by' => $contract->responsible_id,
        'sort' => 1,
    ]);
}

it('serves a PDF attachment inline so the browser can view, print and save it', function () {
    Storage::fake('local');

    $contract = Contract::factory()->create();
    dossierViewer($contract);
    $attachment = dossierFile($contract, 'Скан договора.pdf', 'a.pdf');

    $response = get(route('contracts.attachments.inline', ['contract' => $contract, 'attachment' => $attachment]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    // Inline (not download), with the Cyrillic name in the RFC 5987 form.
    $disposition = (string) $response->headers->get('Content-Disposition');
    expect($disposition)->toStartWith('inline;')
        ->and($disposition)->toContain("filename*=utf-8''%D0%A1%D0%BA%D0%B0%D0%BD");
});

it('forbids the inline file for a user with no access to the contract', function () {
    Storage::fake('local');

    $contract = Contract::factory()->create();
    $attachment = dossierFile($contract, 'a.pdf', 'a.pdf');

    actingAs(User::factory()->create());

    get(route('contracts.attachments.inline', ['contract' => $contract, 'attachment' => $attachment]))
        ->assertForbidden();
});

it('opens a Word attachment through the read-only OnlyOffice viewer', function () {
    Storage::fake('local');
    config(['onlyoffice.callback_host' => 'http://onlyoffice-host']);

    $contract = Contract::factory()->create();
    dossierViewer($contract);
    $attachment = dossierFile($contract, 'Проект договора.docx', 'b.docx');

    get(route('contracts.attachments.viewer', ['contract' => $contract, 'attachment' => $attachment]))
        ->assertOk()
        ->assertSee('"mode":"view"', false)
        ->assertSee('"edit":false', false)
        ->assertSee('"print":true', false)
        ->assertSee('Проект договора.docx');
});

it('returns 404 from the viewer for a non-Word attachment', function () {
    Storage::fake('local');

    $contract = Contract::factory()->create();
    dossierViewer($contract);
    $attachment = dossierFile($contract, 'a.pdf', 'a.pdf');

    get(route('contracts.attachments.viewer', ['contract' => $contract, 'attachment' => $attachment]))
        ->assertNotFound();
});

it('lets the OnlyOffice server fetch the file only with the derived shared key', function () {
    Storage::fake('local');

    $contract = Contract::factory()->create();
    $attachment = dossierFile($contract, 'b.docx', 'b.docx');

    get(route('contract-attachments.document', ['attachment' => $attachment, 'shared_key' => $attachment->sharedKey()]))
        ->assertOk();

    get(route('contract-attachments.document', ['attachment' => $attachment, 'shared_key' => 'wrong']))
        ->assertForbidden();
});

it('maps the open link per type: browser for pdf, OnlyOffice for docx, none for others', function () {
    Storage::fake('local');

    $contract = Contract::factory()->create();
    dossierViewer($contract);

    $pdf = dossierFile($contract, 'a.pdf', 'a.pdf');
    $docx = dossierFile($contract, 'b.docx', 'b.docx');
    $zip = dossierFile($contract, 'c.zip', 'c.zip');

    Livewire::test(ContractAttachmentsTableWidget::class, ['contractId' => $contract->id])
        ->assertActionHasUrl(
            TestAction::make('open')->table($pdf),
            route('contracts.attachments.inline', ['contract' => $contract->id, 'attachment' => $pdf]),
        )
        ->assertActionHasUrl(
            TestAction::make('open')->table($docx),
            route('contracts.attachments.viewer', ['contract' => $contract->id, 'attachment' => $docx]),
        )
        ->assertActionHidden(TestAction::make('open')->table($zip));
});

it('accepts a Word file into the dossier upload', function () {
    Storage::fake('local');

    $contract = Contract::factory()->create();
    $user = dossierViewer($contract);
    Permission::findOrCreate('update_contract', 'web');
    $user->givePermissionTo('update_contract');
    actingAs($user->fresh());

    Livewire::test(ContractAttachmentsTableWidget::class, ['contractId' => $contract->id])
        ->callAction(TestAction::make('uploadAttachments')->table(), [
            'files' => [UploadedFile::fake()->create('proekt-dogovora.docx', 30, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
        ])
        ->assertHasNoActionErrors();

    expect($contract->attachments()->first()->original_name)->toBe('proekt-dogovora.docx');
});
