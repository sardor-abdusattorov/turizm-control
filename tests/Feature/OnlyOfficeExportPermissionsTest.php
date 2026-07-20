<?php

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\ContractTemplate;
use App\Models\Order;
use App\Models\User;
use App\Services\OnlyOffice\OnlyOfficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
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

it('opens the editor in the language picked in the panel, not the .env default', function () {
    // The editor routes live outside the panel, so the language-switch
    // middleware never sets app locale there — the service must read the
    // plugin's session key itself.
    $responsible = User::factory()->create();
    $contract = Contract::factory()->create(['responsible_id' => $responsible->id]);

    app()->setLocale('en');
    session(['locale' => 'ru']);

    $config = app(OnlyOfficeService::class)->editorConfig($contract, $responsible);

    expect($config['editorConfig']['lang'])->toBe('ru');
});

it('maps Uzbek to Russian in the editor — OnlyOffice has no Uzbek interface', function () {
    $responsible = User::factory()->create();
    $contract = Contract::factory()->create(['responsible_id' => $responsible->id]);

    session(['locale' => 'uz']);

    $config = app(OnlyOfficeService::class)->editorConfig($contract, $responsible);

    expect($config['editorConfig']['lang'])->toBe('ru');
});

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

it('lets the current approver edit directly but still locks export while in review', function () {
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

    expect($permissions['edit'])->toBeTrue()
        ->and($permissions['review'])->toBeFalse()
        ->and($permissions['download'])->toBeFalse()
        ->and($permissions['print'])->toBeFalse();
});

it('lets the responsible manager edit a contract that is in review', function () {
    $responsible = User::factory()->create();
    $contract = Contract::factory()->create([
        'responsible_id' => $responsible->id,
        'status' => Contract::STATUS_IN_REVIEW,
    ]);

    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => User::factory()->create()->id,
        'order' => 1,
        'status' => ContractApprover::STATUS_PENDING,
    ]);

    $permissions = editorPermissions($contract->refresh(), $responsible);

    expect($permissions['edit'])->toBeTrue()
        ->and($permissions['review'])->toBeFalse();
});

it('keeps track changes disabled in the editor', function () {
    $responsible = User::factory()->create();
    $contract = Contract::factory()->create([
        'responsible_id' => $responsible->id,
        'status' => Contract::STATUS_DRAFT,
    ]);

    $review = app(OnlyOfficeService::class)
        ->editorConfig($contract, $responsible)['editorConfig']['customization']['review'];

    expect($review['trackChanges'])->toBeFalse()
        ->and($review['showReviewChanges'])->toBeFalse();
});

it('unlocks download and print once the contract is approved', function () {
    $responsible = User::factory()->create();
    $contract = Contract::factory()->approved()->create([
        'responsible_id' => $responsible->id,
    ]);

    $permissions = editorPermissions($contract, $responsible);

    expect($permissions['download'])->toBeTrue()
        ->and($permissions['print'])->toBeTrue();
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

it('does not send a JWT logo so it cannot blank the mounted editor branding', function () {
    $responsible = User::factory()->create();
    $contract = Contract::factory()->create([
        'responsible_id' => $responsible->id,
        'status' => Contract::STATUS_DRAFT,
    ]);

    // Branding is applied by overriding the editor's own logo asset on disk
    // (docker-compose), not via customization.logo — which CE blanks on init.
    $customization = app(OnlyOfficeService::class)->editorConfig($contract, $responsible)['editorConfig']['customization'];

    expect($customization)->not->toHaveKey('logo');
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

it('never offers download or print for contract templates, in any mode', function () {
    $template = ContractTemplate::factory()->create();
    Storage::disk('local')->put($template->template_file, 'fake-docx');

    foreach (['view', 'edit'] as $mode) {
        $permissions = app(OnlyOfficeService::class)
            ->templateEditorConfig($template->fresh(), User::factory()->create(), $mode)['document']['permissions'];

        expect($permissions['download'])->toBeFalse()
            ->and($permissions['print'])->toBeFalse();
    }
});

it('always offers download and print for orders, in any mode', function () {
    $order = Order::factory()->create([
        'file_path' => 'uploads/files/orders/2026/06/order.docx',
    ]);

    foreach (['view', 'edit'] as $mode) {
        $permissions = app(OnlyOfficeService::class)
            ->orderEditorConfig($order, User::factory()->create(), $mode)['document']['permissions'];

        expect($permissions['download'])->toBeTrue()
            ->and($permissions['print'])->toBeTrue();
    }
});
