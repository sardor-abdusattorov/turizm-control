<?php

use App\Models\Contract;
use App\Models\Department;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function departmentWithHead(string $code, string $headName): Department
{
    $head = User::factory()->create(['name' => $headName, 'status' => true]);

    $department = Department::create([
        'code' => $code,
        'name' => ['ru' => ucfirst($code), 'uz' => ucfirst($code), 'en' => ucfirst($code)],
        'head_of_department' => $head->id,
        'sort' => 1,
        'status' => true,
    ]);

    $head->update(['department_id' => $department->id]);

    return $department;
}

beforeEach(function () {
    departmentWithHead('legal', 'Lawyer One');
    departmentWithHead('accounting', 'Accountant One');
    departmentWithHead('direction', 'Director One');

    Settings::set('approval.flow', ['legal', 'accounting', 'direction']);
    clear_settings_cache();
});

it('builds the chain from the settings queue in order, excluding the director', function () {
    $contract = Contract::factory()->create();

    $created = $contract->buildApprovalChainFromFlow();

    // The director is a separate manual final stage and is never auto-added,
    // even though the seeded flow still lists "direction".
    expect($created)->toBe(2);

    $chain = $contract->approvers()->with('user')->orderBy('order')->get();

    expect($chain->pluck('user.name')->all())->toBe([
        'Lawyer One',
        'Accountant One',
    ]);
});

it('respects a reordered approval flow', function () {
    Settings::set('approval.flow', ['accounting', 'legal', 'direction']);
    clear_settings_cache();

    $contract = Contract::factory()->create();
    $contract->buildApprovalChainFromFlow();

    expect($contract->approvers()->with('user')->orderBy('order')->get()->pluck('user.name')->first())
        ->toBe('Accountant One');
});

it('falls back to the first active member when a department has no head', function () {
    $department = Department::firstWhere('code', 'legal');
    User::where('department_id', $department->id)->update(['department_id' => null]);
    $department->update(['head_of_department' => null]);

    User::factory()->create(['name' => 'Legal Member', 'status' => true, 'department_id' => $department->id]);

    $contract = Contract::factory()->create();
    $contract->buildApprovalChainFromFlow();

    expect($contract->approvers()->with('user')->orderBy('order')->first()->user->name)
        ->toBe('Legal Member');
});

it('exposes a readable preview of the global chain without the director', function () {
    $preview = Contract::approvalChainPreview();

    expect($preview)->toHaveCount(2)
        ->and($preview[0])->toContain('Lawyer One')
        ->and($preview[1])->toContain('Accountant One')
        ->and(implode(' ', $preview))->not->toContain('Director One');
});
