<?php

use App\Enums\ProjectType;
use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Filament\Resources\Contracts\Schemas\ContractForm;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Department;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function legacyCreatorActing(): User
{
    $creator = User::factory()->create(['status' => User::STATUS_ACTIVE]);

    foreach (['view_any_contract', 'create_contract'] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $creator->givePermissionTo($ability);
    }

    actingAs($creator->fresh());

    return $creator;
}

it('files an already-signed legacy contract as approved without a chain', function () {
    Storage::fake('local');

    $contractType = ContractType::factory()->create();
    $contact = Contact::factory()->create(['status' => true]);
    $currency = Currency::factory()->create(['status' => true]);

    legacyCreatorActing();

    Livewire::test(CreateContract::class)
        ->fillForm([
            'already_signed' => true,
            'signed_at' => '2025-03-14',
            'number' => 'LEGACY-001',
            'contract_type_id' => $contractType->id,
            'contact_id' => $contact->id,
            'title' => 'Старый бумажный договор',
            'amount' => 5000,
            'currency_id' => $currency->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $contract = Contract::where('number', 'LEGACY-001')->firstOrFail();

    expect($contract->status)->toBe(Contract::STATUS_APPROVED)
        ->and($contract->signed_at?->format('Y-m-d'))->toBe('2025-03-14')
        ->and($contract->approvers()->count())->toBe(0);
});

it('stores scans uploaded on the create form as dossier attachments', function () {
    Storage::fake('local');

    $contractType = ContractType::factory()->create();
    $contact = Contact::factory()->create(['status' => true]);
    $currency = Currency::factory()->create(['status' => true]);

    $creator = legacyCreatorActing();

    Livewire::test(CreateContract::class)
        ->fillForm([
            'already_signed' => true,
            'signed_at' => '2025-03-14',
            'number' => 'LEGACY-002',
            'contract_type_id' => $contractType->id,
            'contact_id' => $contact->id,
            'title' => 'Договор со сканами',
            'amount' => 700,
            'currency_id' => $currency->id,
            'attachment_files' => [
                UploadedFile::fake()->create('scan-dogovor.pdf', 120, 'application/pdf'),
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $contract = Contract::where('number', 'LEGACY-002')->firstOrFail();
    $attachment = $contract->attachments()->first();

    expect($contract->attachments()->count())->toBe(1)
        // The Livewire test transport does not map original names, so assert
        // the stored file instead (the pattern ContractAttachmentsTest set).
        ->and($attachment->original_name)->toEndWith('.pdf')
        ->and($attachment->uploaded_by)->toBe($creator->id)
        ->and(Storage::disk('local')->exists($attachment->file_path))->toBeTrue();
});

it('keeps the normal draft flow when the legacy switch is off', function () {
    Storage::fake('local');

    // Approval is on by default, so a valid chain (legal + accounting) is
    // still required — the legacy switch must not weaken the normal path.
    $legalDept = Department::factory()->create(['code' => 'legal']);
    $accountingDept = Department::factory()->create(['code' => 'accounting']);
    $legal = User::factory()->create(['status' => User::STATUS_ACTIVE, 'department_id' => $legalDept->id]);
    $accounting = User::factory()->create(['status' => User::STATUS_ACTIVE, 'department_id' => $accountingDept->id]);

    $contractType = ContractType::factory()->create();
    $contact = Contact::factory()->create(['status' => true]);
    $currency = Currency::factory()->create(['status' => true]);

    legacyCreatorActing();

    Livewire::test(CreateContract::class)
        ->fillForm([
            'number' => 'DRAFT-001',
            'contract_type_id' => $contractType->id,
            'contact_id' => $contact->id,
            'title' => 'Обычный черновик',
            'amount' => 100,
            'currency_id' => $currency->id,
            'approver_chain' => [$legal->id, $accounting->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $contract = Contract::where('number', 'DRAFT-001')->firstOrFail();

    expect($contract->status)->toBe(Contract::STATUS_DRAFT)
        ->and($contract->signed_at)->toBeNull()
        ->and($contract->approvers()->count())->toBe(2);
});

it('groups project and order options by type and year', function () {
    Project::factory()->international()->create(['name' => 'EXPO-INTL', 'starts_on' => '2026-03-01', 'status' => true]);
    Project::factory()->create(['name' => 'LOCAL-FEST', 'type' => ProjectType::Internal, 'starts_on' => '2026-05-01', 'status' => true]);
    Order::factory()->create(['number' => '06-АФ', 'issued_at' => '2025-01-10', 'status' => true]);

    $projectGroups = invokeProtectedStatic(
        ContractForm::class,
        'projectOptionsGrouped',
    );
    $orderGroups = invokeProtectedStatic(
        ContractForm::class,
        'orderOptions',
    );

    // Every group key carries the type · year context; options nest inside.
    expect(collect($projectGroups)->keys()->filter(fn ($k) => str_contains($k, '2026'))->count())->toBeGreaterThan(0)
        ->and(collect($projectGroups)->flatten()->values()->all())->toContain('EXPO-INTL', 'LOCAL-FEST')
        ->and($orderGroups)->toHaveKey('2025')
        ->and(collect($orderGroups['2025'])->first())->toContain('06-АФ');
});

function invokeProtectedStatic(string $class, string $method): mixed
{
    $ref = new ReflectionMethod($class, $method);
    $ref->setAccessible(true);

    return $ref->invoke(null);
}
