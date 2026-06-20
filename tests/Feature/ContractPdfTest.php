<?php

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use App\Services\Contracts\ContractPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/** Grant the Shield export_contract permission to an existing user. */
function grantExportContract(User $user): User
{
    Permission::findOrCreate('export_contract', 'web');
    $user->givePermissionTo('export_contract');

    return $user->fresh();
}

beforeEach(function () {
    Storage::fake('local');

    config([
        'onlyoffice.public_url' => 'http://onlyoffice',
        'onlyoffice.internal_url' => 'http://onlyoffice',
        'onlyoffice.callback_host' => 'http://nginx',
        'onlyoffice.jwt_secret' => 'test-secret',
    ]);
});

function makeContractWithDocx(ContractStatus $status = Contract::STATUS_APPROVED): Contract
{
    $responsible = User::factory()->create();

    $contract = Contract::factory()->create([
        'responsible_id' => $responsible->id,
        'status' => $status,
    ]);

    Storage::disk('local')->put("contracts/{$contract->id}/draft.docx", 'fake-docx-body');

    return $contract->fresh();
}

it('generates a PDF via OnlyOffice converter and stores it locally', function () {
    Http::fake([
        '*/converter' => Http::response(['fileUrl' => 'http://onlyoffice/cache/files/abc.pdf']),
        '*/cache/files/*' => Http::response('%PDF-fake-binary'),
    ]);

    $contract = makeContractWithDocx();

    $path = app(ContractPdfService::class)->generate($contract);

    expect($path)->not->toBeNull();
    Storage::disk('local')->assertExists("contracts/{$contract->id}/contract.pdf");
    expect(Storage::disk('local')->get("contracts/{$contract->id}/contract.pdf"))
        ->toBe('%PDF-fake-binary');
});

it('reuses the cached PDF on subsequent ensure() calls', function () {
    Http::fake([
        '*/converter' => Http::response(['fileUrl' => 'http://onlyoffice/cache/files/abc.pdf']),
        '*/cache/files/*' => Http::response('%PDF-v1'),
    ]);

    $contract = makeContractWithDocx();
    $service = app(ContractPdfService::class);

    $service->ensure($contract);
    $countAfterFirst = count(Http::recorded());

    $service->ensure($contract);

    expect(count(Http::recorded()))->toBe($countAfterFirst);
});

it('downloads the PDF for the responsible user', function () {
    Http::fake([
        '*/converter' => Http::response(['fileUrl' => 'http://onlyoffice/cache/files/abc.pdf']),
        '*/cache/files/*' => Http::response('%PDF-binary'),
    ]);

    $contract = makeContractWithDocx();
    actingAs(grantExportContract($contract->responsible));

    $response = get(route('contracts.pdf.download', $contract));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('allows an approver in the chain to download the PDF', function () {
    Http::fake([
        '*/converter' => Http::response(['fileUrl' => 'http://onlyoffice/cache/files/abc.pdf']),
        '*/cache/files/*' => Http::response('%PDF-binary'),
    ]);

    $contract = makeContractWithDocx();
    $approver = User::factory()->create();
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $approver->id,
    ]);

    actingAs(grantExportContract($approver));

    get(route('contracts.pdf.download', $contract))->assertOk();
});

it('forbids downloading the PDF without the export_contract permission', function () {
    Http::fake([
        '*/converter' => Http::response(['fileUrl' => 'http://onlyoffice/cache/files/abc.pdf']),
        '*/cache/files/*' => Http::response('%PDF-binary'),
    ]);

    // Responsible can VIEW but cannot EXPORT — export is gated separately.
    $contract = makeContractWithDocx();
    actingAs($contract->responsible);

    get(route('contracts.pdf.download', $contract))->assertForbidden();
});

it('forbids outsiders from downloading the PDF', function () {
    $contract = makeContractWithDocx();
    $outsider = User::factory()->create();

    actingAs($outsider);

    get(route('contracts.pdf.download', $contract))->assertForbidden();
});

it('returns 404 when the docx has not been built yet', function () {
    $contract = Contract::factory()->create([
        'responsible_id' => User::factory(),
        'status' => Contract::STATUS_APPROVED,
    ]);

    actingAs(grantExportContract($contract->responsible));

    get(route('contracts.pdf.download', $contract))->assertNotFound();
});

it('serves the PDF inline for preview', function () {
    Http::fake([
        '*/converter' => Http::response(['fileUrl' => 'http://onlyoffice/cache/files/abc.pdf']),
        '*/cache/files/*' => Http::response('%PDF-binary'),
    ]);

    $contract = makeContractWithDocx(Contract::STATUS_IN_REVIEW);
    actingAs($contract->responsible);

    $response = get(route('contracts.pdf.inline', $contract));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->headers->get('content-disposition'))
        ->toContain('inline');
});
