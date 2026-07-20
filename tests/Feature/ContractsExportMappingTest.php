<?php

use App\Exports\ContractsExport;
use App\Models\Contract;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('numbers rows sequentially and resolves the counterparty for both contact and sponsor deals', function () {
    $regular = Contract::factory()->create(['number' => 'REG-1']);
    $sponsorship = Contract::factory()->sponsorship()->create([
        'number' => 'SP-1',
        'project_id' => Project::factory(),
    ]);

    $export = new ContractsExport(Contract::query()->orderBy('id'));
    $rows = $export->query()->get()->map(fn (Contract $contract): array => $export->map($contract));

    // № is a running row number, never the database id.
    expect($rows->pluck(0)->all())->toBe([1, 2]);

    $byNumber = $rows->keyBy(1);

    expect($byNumber['REG-1'][5])->toBe($regular->contact->name)
        // The sponsorship deal exports its sponsor — not an empty cell.
        ->and($byNumber['SP-1'][5])->toBe($sponsorship->sponsor->name)
        ->and($byNumber['SP-1'][3])->toBe($sponsorship->contractType->title)
        ->and($byNumber['SP-1'][4])->toBe($sponsorship->project->name);
});

it('lists the type, project and counterparty columns in the headings', function () {
    expect((new ContractsExport(Contract::query()))->headings())
        ->toContain(__('app.label.contract_type_single'), __('app.label.project_single'), __('app.label.counterparty'));
});
