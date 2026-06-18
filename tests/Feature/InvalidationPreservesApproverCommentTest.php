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
    $row = ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $approver->id,
        'order' => 1,
        'status' => ContractApprover::STATUS_APPROVED,
        'acted_at' => now()->subHour(),
        'comment' => 'Looks good, ship it.',
    ]);

    // The manager edits a trigger field — the chain should be invalidated
    // but the original approver comment must stay intact.
    $contract->update(['title' => 'Edited title']);

    $row->refresh();

    expect($row->status)->toBe(ContractApprover::STATUS_INVALIDATED)
        ->and($row->comment)->toBe('Looks good, ship it.')
        ->and($row->system_comment)->toBe(__('app.message.invalidated_on_edit'));
});
