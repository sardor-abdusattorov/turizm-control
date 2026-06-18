<?php

use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\ContractTemplate;
use App\Models\Currency;
use App\Models\Department;
use App\Models\OrderType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function approver(int $departmentId): User
{
    return User::factory()->create([
        'status' => User::STATUS_ACTIVE,
        'department_id' => $departmentId,
    ]);
}

function editorAuthor(User $profileDefault): User
{
    $author = User::factory()->create(['status' => User::STATUS_ACTIVE]);

    foreach (['view_any_contract', 'view_contract', 'update_contract'] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $author->givePermissionTo($ability);
    }

    $author->defaultRecipients()->sync([$profileDefault->id]);

    actingAs($author->fresh());

    return $author->fresh();
}

it('keeps the invalidated chain visible and pre-fills the picker from profile defaults', function () {
    $legal = Department::factory()->create(['code' => 'legal']);
    $profileDefault = approver($legal->id);
    $oldApprover = approver($legal->id);

    $author = editorAuthor($profileDefault);

    $orderType = OrderType::factory()->create();
    $template = ContractTemplate::factory()->create(['order_type_id' => $orderType->id, 'status' => true]);

    $contract = Contract::factory()->create([
        'responsible_id' => $author->id,
        'status' => Contract::STATUS_IN_REVIEW,
        'number' => 'C-INV-1',
        'order_type_id' => $orderType->id,
        'contract_template_id' => $template->id,
        'contact_id' => Contact::factory()->create(['status' => true])->id,
        'currency_id' => Currency::factory()->create(['status' => true])->id,
        'amount' => 1000,
    ]);

    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $oldApprover->id,
        'order' => 1,
        'status' => ContractApprover::STATUS_PENDING,
    ]);

    // Author edits a trigger field (title) and saves while it's in review.
    Livewire::test(EditContract::class, ['record' => $contract->id])
        ->fillForm(['title' => 'Edited title'])
        ->call('save')
        ->assertHasNoFormErrors();

    $contract->refresh();

    // 1) The hook flipped status back to DRAFT and the old chain stays as
    //    INVALIDATED audit rows — afterSave must NOT have wiped them.
    expect($contract->status)->toBe(Contract::STATUS_DRAFT);
    expect($contract->approvers()->where('status', ContractApprover::STATUS_INVALIDATED)->count())->toBe(1);

    // 2) Reopen edit — picker pre-fills from the author's profile default
    //    recipient because there are no queued/pending rows left.
    Livewire::test(EditContract::class, ['record' => $contract->id])
        ->assertFormSet(['approver_chain' => [$profileDefault->id]]);
});
