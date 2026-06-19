<?php

use App\Models\ContractTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    config([
        'onlyoffice.public_url' => 'http://onlyoffice',
        'onlyoffice.internal_url' => 'http://onlyoffice',
        'onlyoffice.callback_host' => 'http://nginx',
        'onlyoffice.jwt_secret' => 'test-secret',
    ]);
});

function makeTemplateWithFile(): ContractTemplate
{
    $template = ContractTemplate::factory()->create();

    Storage::disk('local')->put($template->template_file, 'fake-docx');

    return $template->fresh();
}

it('issues a fresh document_key when the template is created', function () {
    $template = ContractTemplate::factory()->create();

    expect($template->document_key)->not->toBeEmpty()
        ->and(strlen($template->document_key))->toBe(20);
});

it('keeps the document_key when template_file is mass-updated, leaving rotation to the OnlyOffice callback', function () {
    $template = ContractTemplate::factory()->create();
    $originalKey = $template->document_key;

    $template->update(['template_file' => 'contract-templates/replaced.docx']);

    expect($template->fresh()->document_key)->toBe($originalKey);
});

it('renders the OnlyOffice editor view for an existing template', function () {
    $template = makeTemplateWithFile();

    actingAs(userWithPermission('view_contract_template'));

    $response = get(route('contract-templates.editor', $template));

    $response->assertOk();
});

it('forbids a user without the template permission from opening the editor', function () {
    $template = makeTemplateWithFile();

    actingAs(User::factory()->create());

    get(route('contract-templates.editor', $template))->assertForbidden();
});

it('returns 404 from the editor when the template file is missing', function () {
    $template = ContractTemplate::factory()->create();

    actingAs(userWithPermission('view_contract_template'));

    get(route('contract-templates.editor', $template))->assertNotFound();
});

it('downloads the template docx via OnlyOffice with a valid shared_key', function () {
    $template = makeTemplateWithFile();

    $response = get(route('onlyoffice.template.document', [
        'template' => $template,
        'shared_key' => $template->document_key,
    ]));

    $response->assertOk();
});

it('rejects OnlyOffice document requests without a matching shared_key', function () {
    $template = makeTemplateWithFile();

    get(route('onlyoffice.template.document', [
        'template' => $template,
        'shared_key' => 'wrong',
    ]))->assertForbidden();
});

it('persists the edited template back to storage on OnlyOffice callback', function () {
    Http::fake([
        '*/edited-template.docx' => Http::response('%edited-binary'),
    ]);

    $template = makeTemplateWithFile();
    $originalKey = $template->document_key;

    post(
        route('onlyoffice.template.callback', [
            'template' => $template,
            'shared_key' => $template->document_key,
        ]),
        [
            'status' => 2,
            'url' => 'http://onlyoffice/cache/files/edited-template.docx',
        ],
    )->assertOk()->assertExactJson(['error' => 0]);

    expect(Storage::disk('local')->get($template->template_file))->toBe('%edited-binary')
        ->and($template->fresh()->document_key)->not->toBe($originalKey);
});

it('keeps the document_key intact on forcesave callbacks during editing', function () {
    Http::fake([
        '*/edited-template.docx' => Http::response('%mid-edit-binary'),
    ]);

    $template = makeTemplateWithFile();
    $originalKey = $template->document_key;

    post(
        route('onlyoffice.template.callback', [
            'template' => $template,
            'shared_key' => $template->document_key,
        ]),
        [
            'status' => 6,
            'url' => 'http://onlyoffice/cache/files/edited-template.docx',
        ],
    )->assertOk()->assertExactJson(['error' => 0]);

    expect(Storage::disk('local')->get($template->template_file))->toBe('%mid-edit-binary')
        ->and($template->fresh()->document_key)->toBe($originalKey);
});

it('rejects OnlyOffice callbacks without a matching shared_key', function () {
    $template = makeTemplateWithFile();

    post(
        route('onlyoffice.template.callback', [
            'template' => $template,
            'shared_key' => 'wrong',
        ]),
        ['status' => 2, 'url' => 'http://onlyoffice/x.docx'],
    )->assertForbidden();
});
