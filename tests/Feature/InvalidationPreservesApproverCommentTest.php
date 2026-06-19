<?php

use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\ContractTemplate;
use App\Models\Currency;
use App\Models\Department;
use App\Models\OrderType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('preserves an approver original comment when the chain is invalidated by an edit', function () {
    $author = User::factory()->create(['status' => User::STATUS_ACTIVE]);
    $legal = Department::factory()->create(['code' => 'legal']);
    $approver = User::factory()->create(['status' => User::STATUS_ACTIVE, 'department_id' => $legal->id]);

    $orderType = OrderType::factory()->create();
    $template = ContractTemplate::factory()->create(['order_type_id' => $orderType->id, 'status' => true]);

    $contract = Contract::factory()->create([
        'responsible_id' => $author->id,
        'status' => Contract::STATUS_IN_REVIEW,
        'order_type_id' => $orderType->id,
        'contract_template_id' => $template->id,
        'contact_id' => Contact::factory()->create(['status' => true])->id,
        'currency_id' => Currency::factory()->create(['status' => true])->id,
        'number' => 'C-INV-PRESERVE',
        'amount' => 1000,
    ]);

    // The first approver already approved with their own comment.
    $approvedAt = now()->subHour();
    $row = ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $approver->id,
        'order' => 1,
        'status' => ContractApprover::STATUS_APPROVED,
        'acted_at' => $approvedAt,
        'comment' => 'Looks good, ship it.',
    ]);

    // The manager edits a trigger field — the chain should be invalidated
    // but the original approver verdict, comment and timestamp must stay
    // intact so the audit trail still reads "Approved · time · comment".
    $contract->update(['title' => 'Edited title']);

    $row->refresh();

    expect($row->status)->toBe(ContractApprover::STATUS_INVALIDATED)
        ->and($row->original_status)->toBe(ContractApprover::STATUS_APPROVED)
        ->and($row->comment)->toBe('Looks good, ship it.')
        ->and($row->system_comment)->toBe(__('app.message.invalidated_on_edit'))
        ->and($row->acted_at->format('Y-m-d H:i:s'))->toBe($approvedAt->format('Y-m-d H:i:s'))
        ->and($row->wasCancelledAfterVerdict())->toBeTrue()
        ->and($row->displayStatus())->toBe(ContractApprover::STATUS_APPROVED);
});

it('does not stamp a verdict onto a queued row cancelled by an edit', function () {
    $author = User::factory()->create(['status' => User::STATUS_ACTIVE]);
    $approver = User::factory()->create(['status' => User::STATUS_ACTIVE]);

    $orderType = OrderType::factory()->create();
    $template = ContractTemplate::factory()->create(['order_type_id' => $orderType->id, 'status' => true]);

    $contract = Contract::factory()->create([
        'responsible_id' => $author->id,
        'status' => Contract::STATUS_IN_REVIEW,
        'order_type_id' => $orderType->id,
        'contract_template_id' => $template->id,
        'contact_id' => Contact::factory()->create(['status' => true])->id,
        'currency_id' => Currency::factory()->create(['status' => true])->id,
        'number' => 'C-INV-QUEUED',
        'amount' => 1000,
    ]);

    $row = ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $approver->id,
        'order' => 1,
        'status' => ContractApprover::STATUS_PENDING,
        'acted_at' => null,
    ]);

    $contract->update(['amount' => 4321]);

    $row->refresh();

    expect($row->status)->toBe(ContractApprover::STATUS_INVALIDATED)
        ->and($row->original_status)->toBeNull()
        ->and($row->wasCancelledAfterVerdict())->toBeFalse()
        ->and($row->acted_at)->not->toBeNull();
});

it('rebuilds a fresh queued chain mirroring the previous one after an in-flow edit', function () {
    $author = User::factory()->create(['status' => User::STATUS_ACTIVE]);
    $first = User::factory()->create(['name' => 'Olim First', 'status' => User::STATUS_ACTIVE]);
    $second = User::factory()->create(['name' => 'Madina Second', 'status' => User::STATUS_ACTIVE]);

    $orderType = OrderType::factory()->create();
    $template = ContractTemplate::factory()->create(['order_type_id' => $orderType->id, 'status' => true]);

    $contract = Contract::factory()->create([
        'responsible_id' => $author->id,
        'status' => Contract::STATUS_IN_REVIEW,
        'order_type_id' => $orderType->id,
        'contract_template_id' => $template->id,
        'contact_id' => Contact::factory()->create(['status' => true])->id,
        'currency_id' => Currency::factory()->create(['status' => true])->id,
        'number' => 'C-INV-REBUILD',
        'amount' => 1000,
    ]);

    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $first->id, 'order' => 1,
        'status' => ContractApprover::STATUS_APPROVED, 'comment' => 'OK from #1',
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $second->id, 'order' => 2,
        'status' => ContractApprover::STATUS_PENDING,
    ]);

    // Trigger an in-flow edit on a re-approval field.
    $contract->update(['amount' => 5000]);

    $rows = $contract->approvers()->get();

    // 4 rows total — two old INVALIDATED + two fresh QUEUED, same users + order.
    expect($rows)->toHaveCount(4)
        ->and($contract->fresh()->status)->toBe(Contract::STATUS_DRAFT)
        ->and($rows->where('status', ContractApprover::STATUS_INVALIDATED))->toHaveCount(2)
        ->and($rows->where('status', ContractApprover::STATUS_QUEUED))->toHaveCount(2);

    $newRows = $rows->where('status', ContractApprover::STATUS_QUEUED)->sortBy('order')->values();
    expect($newRows->pluck('user_id')->all())->toBe([$first->id, $second->id])
        ->and($newRows->pluck('order')->all())->toBe([1, 2]);
});
