<?php

use App\Models\ContractApprover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('resolves the current approver from the eager-loaded relation without a query', function () {
    $pending = ContractApprover::factory()->create(['order' => 2]);
    ContractApprover::factory()->approved()->create([
        'contract_id' => $pending->contract_id,
        'order' => 1,
    ]);

    $contract = $pending->contract->fresh()->load('activeApprovers');

    DB::enableQueryLog();
    $current = $contract->currentApprover();

    expect(DB::getQueryLog())->toBeEmpty()
        ->and($current?->id)->toBe($pending->id);
});
