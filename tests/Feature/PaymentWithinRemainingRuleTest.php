<?php

use App\Models\Contract;
use App\Rules\PaymentWithinRemaining;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function runRule(?Contract $contract, mixed $value): ?string
{
    $message = null;

    (new PaymentWithinRemaining($contract))->validate(
        'percent',
        $value,
        function (string $msg) use (&$message): void {
            $message = $msg;
        },
    );

    return $message;
}

it('fails when the percent exceeds the contract remaining', function () {
    $contract = Contract::factory()->create(['paid_percent' => 80]);

    expect(runRule($contract, 25))->not->toBeNull();
});

it('passes when the percent fits within the remaining', function () {
    $contract = Contract::factory()->create(['paid_percent' => 80]);

    expect(runRule($contract, 20))->toBeNull();
});

it('is a no-op when there is no contract to validate against', function () {
    expect(runRule(null, 999))->toBeNull();
});
