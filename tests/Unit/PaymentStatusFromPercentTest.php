<?php

use App\Enums\PaymentStatus;

it('returns NotPaid for zero and negative percent', function (float $percent) {
    expect(PaymentStatus::fromPercent($percent))->toBe(PaymentStatus::NotPaid);
})->with([
    'exact zero' => [0.0],
    'one cent below zero (defensive)' => [-0.01],
    'large negative (defensive)' => [-50.0],
]);

it('returns PartiallyPaid for any value strictly between 0 and 100', function (float $percent) {
    expect(PaymentStatus::fromPercent($percent))->toBe(PaymentStatus::PartiallyPaid);
})->with([
    'one cent above zero' => [0.01],
    'one third' => [33.33],
    'half' => [50.0],
    'just under full' => [99.99],
]);

it('returns FullyPaid at or above 100', function (float $percent) {
    expect(PaymentStatus::fromPercent($percent))->toBe(PaymentStatus::FullyPaid);
})->with([
    'exact hundred' => [100.0],
    'rounding crumb over' => [100.000001],
    'sum of three thirds with rounding' => [33.33 + 33.33 + 33.34],
]);
