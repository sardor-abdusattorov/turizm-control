<?php

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\ContractTemplate;
use App\Models\Settings;
use App\Models\User;
use App\Services\OnlyOffice\OnlyOfficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Cache::flush();

    config([
        'onlyoffice.public_url' => 'http://onlyoffice',
        'onlyoffice.internal_url' => 'http://onlyoffice',
        'onlyoffice.callback_host' => 'http://nginx',
        'onlyoffice.jwt_secret' => 'test-secret',
        'app.url' => 'http://turizm.test',
    ]);
});

function editorPermissions(Contract $contract, User $user): array
{
    return app(OnlyOfficeService::class)->editorConfig($contract, $user)['document']['permissions'];
}

it('locks download and print while a draft contract is still in progress', function () {
    $responsible = User::factory()->create();
    $contract = Contract::factory()->create([
        'responsible_id' => $responsible->id,
        'status' => Contract::STATUS_DRAFT,
    ]);

    $permissions = editorPermissions($contract, $responsible);

    expect($permissions['edit'])->toBeTrue()
        ->and($permissions['download'])->toBeFalse()
        ->and($permissions['print'])->toBeFalse();
});

it('locks export while the contract is in review for the current approver', function () {
    $approver = User::factory()->create();
    $contract = Contract::factory()->create([
        'responsible_id' => User::factory()->create()->id,
        'status' => Contract::STATUS_IN_REVIEW,
    ]);

    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $approver->id,
        'order' => 1,
        'status' => ContractApprover::STATUS_PENDING,
    ]);

    $permissions = editorPermissions($contract->refresh(), $approver);

    expect($permissions['review'])->toBeTrue()
        ->and($permissions['edit'])->toBeFalse()
        ->and($permissions['download'])->toBeFalse()
        ->and($permissions['print'])->toBeFalse();
});

it('keeps approved contracts read-only inside OnlyOffice (export goes through the PDF button)', function () {
    $responsible = User::factory()->create();
    $contract = Contract::factory()->approved()->create([
        'responsible_id' => $responsible->id,
    ]);

    // An approved contract opens in view mode, so the in-editor export
    // controls are hidden — the dedicated "Download PDF" action is the
    // proper channel for the finished document.
    $permissions = editorPermissions($contract, $responsible);

    expect($permissions['download'])->toBeFalse()
        ->and($permissions['print'])->toBeFalse();
});

it('always lets a super admin export, even on a draft', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

    $contract = Contract::factory()->create([
        'responsible_id' => User::factory()->create()->id,
        'status' => Contract::STATUS_DRAFT,
    ]);

    $permissions = editorPermissions($contract, $admin->fresh());

    expect($permissions['download'])->toBeTrue()
        ->and($permissions['print'])->toBeTrue();
});

it('brands the editor with the organization logo when one is configured', function () {
    Settings::create(['key' => 'organization.logo_path', 'value' => 'organization/logo.png']);
    Cache::flush();

    $responsible = User::factory()->create();
    $contract = Contract::factory()->create([
        'responsible_id' => $responsible->id,
        'status' => Contract::STATUS_DRAFT,
    ]);

    $customization = app(OnlyOfficeService::class)->editorConfig($contract, $responsible)['editorConfig']['customization'];

    expect($customization)->toHaveKey('logo')
        ->and($customization['logo']['image'])->toContain('organization/logo.png');
});

it('falls back to the application brand logo when no organization logo is set', function () {
    $responsible = User::factory()->create();
    $contract = Contract::factory()->create([
        'responsible_id' => $responsible->id,
        'status' => Contract::STATUS_DRAFT,
    ]);

    $customization = app(OnlyOfficeService::class)->editorConfig($contract, $responsible)['editorConfig']['customization'];

    expect($customization)->toHaveKey('logo')
        ->and($customization['logo']['image'])->toContain('images/logo.png');
});

it('trims the editor chrome down to the document', function () {
    $responsible = User::factory()->create();
    $contract = Contract::factory()->create([
        'responsible_id' => $responsible->id,
        'status' => Contract::STATUS_DRAFT,
    ]);

    $customization = app(OnlyOfficeService::class)->editorConfig($contract, $responsible)['editorConfig']['customization'];

    expect($customization['plugins'])->toBeFalse()
        ->and($customization['help'])->toBeFalse();
});

it('allows export when a template is opened in edit mode', function () {
    $template = ContractTemplate::factory()->create();
    Storage::disk('public')->put($template->template_file, 'fake-docx');

    $permissions = app(OnlyOfficeService::class)
        ->templateEditorConfig($template->fresh(), User::factory()->create(), 'edit')['document']['permissions'];

    expect($permissions['download'])->toBeTrue()
        ->and($permissions['print'])->toBeTrue();
});

it('hides export when a template is opened in view mode', function () {
    $template = ContractTemplate::factory()->create();
    Storage::disk('public')->put($template->template_file, 'fake-docx');

    $permissions = app(OnlyOfficeService::class)
        ->templateEditorConfig($template->fresh(), User::factory()->create(), 'view')['document']['permissions'];

    expect($permissions['download'])->toBeFalse()
        ->and($permissions['print'])->toBeFalse();
});
