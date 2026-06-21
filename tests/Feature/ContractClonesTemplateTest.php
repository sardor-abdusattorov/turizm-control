<?php

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Services\Documents\ContractPlaceholderValues;
use App\Services\Documents\TemplateFiller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makeMinimalDocx(string $bodyText): string
{
    $path = tempnam(sys_get_temp_dir(), 'docx');

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString(
        'word/document.xml',
        '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        .'<w:body><w:p><w:r><w:t>'.$bodyText.'</w:t></w:r></w:p></w:body></w:document>'
    );
    $zip->close();

    return $path;
}

function docxBody(string $absolutePath): string
{
    $zip = new ZipArchive;
    $zip->open($absolutePath);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();

    return $xml;
}

it('clones and fills the template into a separate contract file, leaving the template untouched', function () {
    Storage::fake('local');

    // A template with a placeholder, stored on the private (local) disk.
    $templatePath = 'uploads/files/contract-templates/main.docx';
    Storage::disk('local')->put($templatePath, file_get_contents(makeMinimalDocx('Contract {{number}}')));
    $templateHashBefore = md5(Storage::disk('local')->get($templatePath));

    $template = ContractTemplate::factory()->create(['template_file' => $templatePath]);
    $contract = Contract::factory()->create([
        'contract_template_id' => $template->id,
        'number' => 'C-777',
    ]);

    $contract->buildDocumentFromTemplate(app(TemplateFiller::class), app(ContractPlaceholderValues::class));

    // 1) The template file is byte-for-byte unchanged.
    expect(md5(Storage::disk('local')->get($templatePath)))->toBe($templateHashBefore);

    // 2) The contract gets its OWN document on the private (local) disk.
    $contract->refresh();
    expect($contract->document_file)->toBe("uploads/files/contracts/{$contract->id}/draft.docx")
        ->and(Storage::disk('local')->exists($contract->document_file))->toBeTrue();

    // 3) The clone has the placeholder filled; the template keeps it raw.
    $cloneBody = docxBody(Storage::disk('local')->path($contract->document_file));
    expect($cloneBody)->toContain('C-777')->not->toContain('{{number}}');

    $templateBody = docxBody(Storage::disk('local')->path($templatePath));
    expect($templateBody)->toContain('{{number}}');
});

it('gives the contract a different document_key than the template', function () {
    Storage::fake('local');

    $templatePath = 'uploads/files/contract-templates/main.docx';
    Storage::disk('local')->put($templatePath, file_get_contents(makeMinimalDocx('Contract {{number}}')));

    $template = ContractTemplate::factory()->create(['template_file' => $templatePath]);
    $contract = Contract::factory()->create([
        'contract_template_id' => $template->id,
        'number' => 'C-778',
    ]);

    $contract->buildDocumentFromTemplate(app(TemplateFiller::class), app(ContractPlaceholderValues::class));

    expect($contract->fresh()->document_key)->not->toBe($template->document_key);
});
