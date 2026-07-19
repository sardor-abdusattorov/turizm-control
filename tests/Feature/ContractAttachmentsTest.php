<?php

use App\Enums\ContractAttachmentType;
use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Filament\Resources\Contracts\Pages\ViewContract;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function attachmentManager(Contract $contract, array $abilities = ['view_any_contract', 'view_contract', 'update_contract']): User
{
    $user = $contract->responsible;

    foreach ($abilities as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }

    actingAs($user->fresh());

    return $user;
}

/**
 * The edit form demands a contract type and a valid approval chain
 * (legal + accounting) before it saves — provide both.
 *
 * @return array<string, mixed>
 */
function validEditFormFill(): array
{
    $legalDept = Department::factory()->create(['code' => 'legal']);
    $accountingDept = Department::factory()->create(['code' => 'accounting']);
    $legal = User::factory()->create(['status' => User::STATUS_ACTIVE, 'department_id' => $legalDept->id]);
    $accounting = User::factory()->create(['status' => User::STATUS_ACTIVE, 'department_id' => $accountingDept->id]);

    return [
        'contract_type_id' => ContractType::factory()->create()->id,
        'approver_chain' => [$legal->id, $accounting->id],
    ];
}

it('uploads dossier files through the edit form', function () {
    Storage::fake('local');

    $contract = Contract::factory()->create();
    attachmentManager($contract);

    Livewire::test(EditContract::class, ['record' => $contract->id])
        ->fillForm([
            ...validEditFormFill(),
            'attachment_files' => [UploadedFile::fake()->create('SWIFT MT103.pdf', 120, 'application/pdf')],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $attachment = $contract->attachments()->first();

    expect($attachment)->not->toBeNull()
        ->and($attachment->original_name)->toBe('SWIFT MT103.pdf')
        ->and($attachment->uploaded_by)->toBe($contract->responsible_id);

    Storage::disk('local')->assertExists($attachment->file_path);
});

it('keeps existing attachments and appends the new upload after them', function () {
    Storage::fake('local');

    $contract = Contract::factory()->create();
    attachmentManager($contract);

    $contract->attachments()->create([
        'file_path' => 'uploads/files/contract-attachments/old.pdf',
        'original_name' => 'old.pdf',
        'size' => 3,
        'sort' => 1,
    ]);

    Livewire::test(EditContract::class, ['record' => $contract->id])
        ->fillForm([
            ...validEditFormFill(),
            'attachment_files' => [UploadedFile::fake()->create('new.pdf', 10, 'application/pdf')],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $attachments = $contract->attachments()->orderBy('sort')->get();

    expect($attachments)->toHaveCount(2)
        ->and($attachments->first()->original_name)->toBe('old.pdf')
        ->and($attachments->last()->sort)->toBe(2);
});

it('deletes an attachment together with its file', function () {
    Storage::fake('local');

    $contract = Contract::factory()->create();
    attachmentManager($contract);

    $path = 'uploads/files/contract-attachments/'.$contract->id.'/act.pdf';
    Storage::disk('local')->put($path, 'pdf');

    $attachment = $contract->attachments()->create([
        'type' => ContractAttachmentType::Act->value,
        'file_path' => $path,
        'original_name' => 'act.pdf',
        'size' => 3,
        'sort' => 1,
    ]);

    Livewire::test(ViewContract::class, ['record' => $contract->id])
        ->callAction('deleteAttachment', arguments: ['attachment' => $attachment->id]);

    expect($contract->attachments()->count())->toBe(0);
    Storage::disk('local')->assertMissing($path);
});

it('removes attachment files when the contract itself is deleted', function () {
    Storage::fake('local');

    $contract = Contract::factory()->create();

    $path = 'uploads/files/contract-attachments/'.$contract->id.'/scan.pdf';
    Storage::disk('local')->put($path, 'pdf');

    $contract->attachments()->create([
        'file_path' => $path,
        'original_name' => 'scan.pdf',
        'size' => 3,
        'sort' => 1,
    ]);

    $contract->delete();

    Storage::disk('local')->assertMissing($path);
});

it('uploads dossier scans from the view page too', function () {
    Storage::fake('local');

    $contract = Contract::factory()->create();
    attachmentManager($contract);

    Livewire::test(ViewContract::class, ['record' => $contract->id])
        ->callAction('uploadAttachments', [
            'files' => [UploadedFile::fake()->create('proposal.pdf', 40, 'application/pdf')],
        ])
        ->assertHasNoActionErrors();

    // The dossier must list the file under the name the user uploaded, not
    // the random hash Filament stores it as on disk.
    expect($contract->attachments()->count())->toBe(1)
        ->and($contract->attachments()->first()->original_name)->toBe('proposal.pdf');
});

it('keeps uploading open after full approval — SWIFT and act arrive later', function () {
    Storage::fake('local');

    // A finalized contract can no longer be edited, yet its dossier must still
    // accept the signed scan, SWIFT slip and act that arrive post-approval.
    $contract = Contract::factory()->create();
    $contract->forceFill(['status' => Contract::STATUS_APPROVED])->saveQuietly();

    attachmentManager($contract);

    expect($contract->fresh()->canBeEditedBy())->toBeFalse();

    Livewire::test(ViewContract::class, ['record' => $contract->id])
        ->callAction('uploadAttachments', [
            'files' => [UploadedFile::fake()->create('SWIFT MT103.pdf', 60, 'application/pdf')],
        ])
        ->assertHasNoActionErrors();

    // Filing the SWIFT slip must not knock the contract off Approved.
    expect($contract->fresh()->status)->toBe(Contract::STATUS_APPROVED)
        ->and($contract->attachments()->count())->toBe(1);
});

it('hides the upload action from users without the update permission', function () {
    Storage::fake('local');

    $contract = Contract::factory()->create();
    attachmentManager($contract, ['view_any_contract', 'view_contract']);

    Livewire::test(ViewContract::class, ['record' => $contract->id])
        ->assertActionHidden('uploadAttachments');
});
