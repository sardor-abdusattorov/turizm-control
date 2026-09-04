<?php

use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Livewire\AttachmentPanel;
use App\Models\PressTour;
use Filament\Forms\Components\FileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function isCompactDocumentList(FileUpload $field): bool
{
    return $field->getPanelLayout() !== 'grid'
        && $field->getImagePreviewHeight() === '120'
        && $field->getChildSchema(FileUpload::BELOW_CONTENT_SCHEMA_KEY) === null;
}

it('lists the contract dossier as compact rows without a hint', function () {
    actingAs(userWithPermission('view_any_contract', 'create_contract'));

    Livewire::test(CreateContract::class)
        ->assertFormFieldExists('attachment_files', 'form', fn (FileUpload $field): bool => isCompactDocumentList($field));
});

it('lists payment proofs as compact rows without a hint', function () {
    actingAs(userWithPermission('view_any_payment', 'create_payment'));

    Livewire::test(CreatePayment::class)
        ->assertFormFieldExists('screenshots', 'form', fn (FileUpload $field): bool => isCompactDocumentList($field));
});

it('lists press tour documents as compact rows without a hint', function () {
    Storage::fake('local');
    actingAs(userWithPermission('view_any_press_tour', 'view_press_tour'));

    $tour = PressTour::factory()->create();

    Livewire::test(AttachmentPanel::class, ['variant' => 'press-tour-documents', 'recordId' => $tour->id])
        ->assertFormFieldExists('document_files', 'form', fn (FileUpload $field): bool => isCompactDocumentList($field));
});
