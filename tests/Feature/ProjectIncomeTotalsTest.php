<?php

use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAs(userWithPermission('view_all_contracts'));
});

it('computes the per-currency income totals with derived paid', function () {
    $project = Project::factory()->create();
    $uzs = Currency::factory()->create(['short_name' => 'UZS']);
    $feeType = ContractType::factory()->income()->create();

    Contract::factory()->create([
        'project_id' => $project->id,
        'contract_type_id' => $feeType->id,
        'currency_id' => $uzs->id,
        'amount' => 1000,
        'paid_percent' => 50,
        'status' => Contract::STATUS_APPROVED,
    ]);

    $fees = $project->incomeTotalsByCurrency(false);

    expect($fees)->toHaveCount(1)
        ->and($fees->first()['currency'])->toBe('UZS')
        ->and($fees->first()['total'])->toBe(1000.0)
        ->and($fees->first()['paid'])->toBe(500.0);
});

it('builds the grouped per-currency queries without an inherited ORDER BY (MySQL only_full_group_by safe)', function () {
    $project = Project::factory()->create();
    $uzs = Currency::factory()->create(['short_name' => 'UZS']);
    $feeType = ContractType::factory()->income()->create();

    Contract::factory()->create([
        'project_id' => $project->id,
        'contract_type_id' => $feeType->id,
        'currency_id' => $uzs->id,
        'amount' => 1000,
        'paid_percent' => 40,
        'status' => Contract::STATUS_APPROVED,
    ]);
    Contract::factory()->sponsorship()->create([
        'project_id' => $project->id,
        'currency_id' => $uzs->id,
        'amount' => 2000,
        'paid_percent' => 25,
        'status' => Contract::STATUS_APPROVED,
    ]);

    DB::enableQueryLog();
    $project->incomeTotalsByCurrency(false);
    $project->incomeTotalsByCurrency(true);
    $project->visibleContractTotalsByCurrency();

    $grouped = collect(DB::getQueryLog())
        ->pluck('query')
        ->map(fn (string $sql): string => strtolower($sql))
        ->filter(fn (string $sql): bool => str_contains($sql, 'group by'));

    expect($grouped)->not->toBeEmpty();
    $grouped->each(fn (string $sql) => expect($sql)->not->toContain('order by'));
});
