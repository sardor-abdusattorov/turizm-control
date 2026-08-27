<?php

use App\Enums\ContractAttachmentType;
use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Livewire\AttachmentPanel;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
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

    Storage::disk('local')->put('uploads/files/contract-attachments/old.pdf', 'x');
    $contract->attachments()->create([
        'file_path' => 'uploads/files/contract-attachments/old.pdf',
        'original_name' => 'old.pdf',
        'size' => 3,
        'sort' => 1,
    ]);

    // The edit field prefills with the stored dossier — keeping the old path
    // in the submitted state preserves it, the UploadedFile lands on top.
    Livewire::test(EditContract::class, ['record' => $contract->id])
        ->assertFormSet(['attachment_files' => ['uploads/files/contract-attachments/old.pdf']])
        ->fillForm([
            ...validEditFormFill(),
            'attachment_files' => [
                'uploads/files/contract-attachments/old.pdf',
                UploadedFile::fake()->create('new.pdf', 10, 'application/pdf'),
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $attachments = $contract->attachments()->orderBy('sort')->get();

    expect($attachments)->toHaveCount(2)
        ->and($attachments->first()->original_name)->toBe('old.pdf')
        ->and($attachments->last()->sort)->toBe(2);
});

it('replaces a file on the edit form: the old attachment and its file are gone', function () {
    Storage::fake('local');

    $contract = Contract::factory()->create();
    attachmentManager($contract);

    Storage::disk('local')->put('uploads/files/contract-attachments/old-scan.pdf', 'old');
    $old = $contract->attachments()->create([
        'file_path' => 'uploads/files/contract-attachments/old-scan.pdf',
        'original_name' => 'old-scan.pdf', 'size' => 3, 'sort' => 1,
    ]);

    // Submitting only the fresh upload = the old chip was removed → replace.
    Livewire::test(EditContract::class, ['record' => $contract->id])
        ->fillForm([
            ...validEditFormFill(),
            'attachment_files' => [UploadedFile::fake()->create('new-scan.pdf', 10, 'application/pdf')],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $attachments = $contract->attachments()->get();

    expect($attachments)->toHaveCount(1)
        ->and($attachments->first()->id)->not->toBe($old->id)
        ->and($attachments->first()->original_name)->toBe('new-scan.pdf');
    Storage::disk('local')->assertMissing('uploads/files/contract-attachments/old-scan.pdf');
    Storage::disk('local')->assertExists($attachments->first()->file_path);
});

it('deletes an attachment whose chip was removed on the edit form', function () {
    Storage::fake('local');

    $contract = Contract::factory()->create();
    attachmentManager($contract);

    Storage::disk('local')->put('uploads/files/contract-attachments/keep.pdf', 'k');
    Storage::disk('local')->put('uploads/files/contract-attachments/drop.pdf', 'd');
    $keep = $contract->attachments()->create([
        'file_path' => 'uploads/files/contract-attachments/keep.pdf',
        'original_name' => 'keep.pdf', 'size' => 1, 'sort' => 1,
    ]);
    $drop = $contract->attachments()->create([
        'file_path' => 'uploads/files/contract-attachments/drop.pdf',
        'original_name' => 'drop.pdf', 'size' => 1, 'sort' => 2,
    ]);

    Livewire::test(EditContract::class, ['record' => $contract->id])
        ->fillForm([
            ...validEditFormFill(),
            'attachment_files' => [$keep->file_path],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($contract->attachments()->pluck('id')->all())->toBe([$keep->id]);
    Storage::disk('local')->assertExists($keep->file_path);
    Storage::disk('local')->assertMissing($drop->file_path);
});

it('deletes an attachment together with its file', function () {
    Storage::fake('local');

    $contract = Contract::factory()->create();
    attachmentManager($contract);

    $path = 'uploads/files/contract-attachments/'.$contract->id.'/act.pdf';
    Storage::disk('local')->put($path, 'pdf');

    $contract->attachments()->create([
        'type' => ContractAttachmentType::Act->value,
        'file_path' => $path,
        'original_name' => 'act.pdf',
        'size' => 3,
        'sort' => 1,
    ]);

    // Removing the chip from the dossier panel and saving drops the row —
    // and the model's deleting hook takes the file with it.
    Livewire::test(AttachmentPanel::class, ['variant' => 'contract-dossier', 'recordId' => $contract->id])
        ->assertFormSet(['attachment_files' => [$path]])
        ->fillForm(['attachment_files' => []])
        ->call('save');

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

it('uploads dossier scans from the view page panel too', function () {
    Storage::fake('local');

    $contract = Contract::factory()->create();
    attachmentManager($contract);

    Livewire::test(AttachmentPanel::class, ['variant' => 'contract-dossier', 'recordId' => $contract->id])
        ->fillForm([
            'attachment_files' => [UploadedFile::fake()->create('proposal.pdf', 40, 'application/pdf')],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    // The dossier must list the file under the name the user uploaded, not
    // the random hash Filament stores it as on disk.
    expect($contract->attachments()->count())->toBe(1)
        ->and($contract->attachments()->first()->original_name)->toBe('proposal.pdf');
});

it('keeps uploading open after full approval — SWIFT and act arrive later', function () {
    Storage::fake('local');

    // The dossier accepts the signed scan, SWIFT slip and act that arrive
    // post-approval — independently of the edit page (which the author may
    // also reopen for legacy typo fixes).
    $contract = Contract::factory()->create();
    $contract->forceFill(['status' => Contract::STATUS_APPROVED])->saveQuietly();

    attachmentManager($contract);

    Livewire::test(AttachmentPanel::class, ['variant' => 'contract-dossier', 'recordId' => $contract->id])
        ->fillForm([
            'attachment_files' => [UploadedFile::fake()->create('SWIFT MT103.pdf', 60, 'application/pdf')],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    // Filing the SWIFT slip must not knock the contract off Approved.
    expect($contract->fresh()->status)->toBe(Contract::STATUS_APPROVED)
        ->and($contract->attachments()->count())->toBe(1);
});

it('locks the dossier panel for users without the update permission', function () {
    Storage::fake('local');

    $contract = Contract::factory()->create();
    attachmentManager($contract, ['view_any_contract', 'view_contract']);

    $panel = Livewire::test(AttachmentPanel::class, ['variant' => 'contract-dossier', 'recordId' => $contract->id]);

    expect($panel->instance()->canManage())->toBeFalse()
        // The upload endpoint itself refuses a locked panel, so a forged
        // _startUpload cannot even park a temporary file.
        ->and($panel->instance()->isFileUploadForSchemaComponent('data.attachment_files'))->toBeFalse();

    $panel->assertFormFieldDisabled('attachment_files')
        // The save button is merely hidden — a forged call is refused outright.
        ->call('save')->assertForbidden();

    expect($contract->attachments()->count())->toBe(0);
});

it('refuses a path belonging to another record and leaves its file alone', function () {
    Storage::fake('local');

    // Everything in the app shares one private disk. Without path
    // authorisation a manager could name a foreign file, mint a signed URL
    // for it, and unlink it by removing the chip again.
    $mine = Contract::factory()->create();
    attachmentManager($mine);

    $foreign = Contract::factory()->create();
    $foreignPath = 'uploads/files/contract-attachments/'.$foreign->id.'/secret.pdf';
    Storage::disk('local')->put($foreignPath, 'pdf');
    $foreign->attachments()->create([
        'file_path' => $foreignPath,
        'original_name' => 'secret.pdf',
        'size' => 3,
        'sort' => 1,
    ]);

    Livewire::test(AttachmentPanel::class, ['variant' => 'contract-dossier', 'recordId' => $mine->id])
        ->fillForm(['attachment_files' => [$foreignPath]])
        ->call('save')
        ->assertHasFormErrors(['attachment_files']);

    expect($mine->attachments()->count())->toBe(0)
        ->and($foreign->attachments()->count())->toBe(1);

    Storage::disk('local')->assertExists($foreignPath);
});

it('will not let the panel be repointed at another record after mounting', function () {
    Storage::fake('local');

    // mount() authorises the record once; without #[Locked] the client could
    // simply set a different id on the next request and skip that gate.
    $mine = Contract::factory()->create();
    attachmentManager($mine);

    $other = Contract::factory()->create();

    expect(fn () => Livewire::test(AttachmentPanel::class, ['variant' => 'contract-dossier', 'recordId' => $mine->id])
        ->set('recordId', $other->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('saving the dossier panel untouched changes nothing', function () {
    Storage::fake('local');

    // The single most destructive failure mode this panel could have: the
    // submitted path list IS the dossier, so a save that arrives with empty
    // state would wipe every file. Mounting must prefill it.
    $contract = Contract::factory()->create();
    attachmentManager($contract);

    foreach (['scan.pdf', 'act.pdf'] as $index => $name) {
        $path = 'uploads/files/contract-attachments/'.$contract->id.'/'.$name;
        Storage::disk('local')->put($path, 'pdf');
        $contract->attachments()->create([
            'file_path' => $path,
            'original_name' => $name,
            'size' => 3,
            'sort' => $index + 1,
        ]);
    }

    $before = $contract->attachments()->pluck('id')->all();

    Livewire::test(AttachmentPanel::class, ['variant' => 'contract-dossier', 'recordId' => $contract->id])
        ->call('save');

    expect($contract->attachments()->pluck('id')->all())->toBe($before);

    foreach ($contract->attachments as $attachment) {
        Storage::disk('local')->assertExists($attachment->file_path);
    }
});

it('keeps an attachment whose file went missing from disk', function () {
    Storage::fake('local');

    // FileUpload silently drops a chip whose file is gone; that absence must
    // not read as a deliberate removal and take the record with it.
    $contract = Contract::factory()->create();
    attachmentManager($contract);

    $contract->attachments()->create([
        'file_path' => 'uploads/files/contract-attachments/'.$contract->id.'/vanished.pdf',
        'original_name' => 'vanished.pdf',
        'size' => 3,
        'sort' => 1,
    ]);

    Livewire::test(AttachmentPanel::class, ['variant' => 'contract-dossier', 'recordId' => $contract->id])
        ->assertFormSet(['attachment_files' => []])
        ->call('save');

    expect($contract->attachments()->count())->toBe(1);
});

it('refuses to mount the dossier panel for a contract the user may not view', function () {
    Storage::fake('local');

    // recordId comes from the client, so the panel gates itself instead of
    // trusting whichever page embedded it.
    $contract = Contract::factory()->create();
    $stranger = User::factory()->create();
    actingAs($stranger);

    Livewire::test(AttachmentPanel::class, ['variant' => 'contract-dossier', 'recordId' => $contract->id])
        ->assertForbidden();
});

it('freezes the dossier while the contract is under approval', function (mixed $status) {
    Storage::fake('local');

    // Approvers must review a fixed set of files — no uploads or deletes may
    // change the dossier mid-approval, even for users who normally manage it.
    $contract = Contract::factory()->create();
    $contract->forceFill(['status' => $status])->saveQuietly();

    attachmentManager($contract);

    $panel = Livewire::test(AttachmentPanel::class, ['variant' => 'contract-dossier', 'recordId' => $contract->id]);

    expect($panel->instance()->canManage())->toBeFalse()
        ->and($panel->instance()->lockedNotice())->toBe(__('app.message.dossier_frozen_in_review'))
        ->and($panel->instance()->isFileUploadForSchemaComponent('data.attachment_files'))->toBeFalse();

    $panel->assertFormFieldDisabled('attachment_files')
        ->call('save')->assertForbidden();

    expect($contract->attachments()->count())->toBe(0);
})->with([
    'regular chain review' => [Contract::STATUS_IN_REVIEW],
    'awaiting director' => [Contract::STATUS_PENDING_DIRECTOR],
    'director reviewing' => [Contract::STATUS_IN_REVIEW_DIRECTOR],
]);
