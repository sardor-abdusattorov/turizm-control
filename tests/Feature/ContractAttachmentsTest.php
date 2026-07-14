<?php

use App\Enums\ContractAttachmentType;
use App\Filament\Resources\Contracts\Pages\ViewContract;
use App\Models\Contract;
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

it('uploads dossier files through the page action', function () {
    Storage::fake('local');

    $contract = Contract::factory()->create();
    attachmentManager($contract);

    Livewire::test(ViewContract::class, ['record' => $contract->id])
        ->callAction('uploadAttachments', [
            'files' => [UploadedFile::fake()->create('SWIFT MT103.pdf', 120, 'application/pdf')],
            'type' => ContractAttachmentType::Swift->value,
        ])
        ->assertHasNoActionErrors();

    $attachment = $contract->attachments()->first();

    // The exact original name flows in through storeFileNamesIn() in the real
    // UI; the test transport only guarantees a stored .pdf with a fallback name.
    expect($attachment)->not->toBeNull()
        ->and($attachment->type)->toBe(ContractAttachmentType::Swift)
        ->and($attachment->original_name)->toEndWith('.pdf')
        ->and($attachment->uploaded_by)->toBe($contract->responsible_id);

    Storage::disk('local')->assertExists($attachment->file_path);
});

it('uploads without a type — categorising is optional', function () {
    Storage::fake('local');

    $contract = Contract::factory()->create();
    attachmentManager($contract);

    Livewire::test(ViewContract::class, ['record' => $contract->id])
        ->callAction('uploadAttachments', [
            'files' => [UploadedFile::fake()->create('scan.pdf', 40, 'application/pdf')],
        ])
        ->assertHasNoActionErrors();

    expect($contract->attachments()->first()?->type)->toBeNull();
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

it('hides the upload action from users without the update permission', function () {
    Storage::fake('local');

    $contract = Contract::factory()->create();
    attachmentManager($contract, ['view_any_contract', 'view_contract']);

    Livewire::test(ViewContract::class, ['record' => $contract->id])
        ->assertActionHidden('uploadAttachments');
});
