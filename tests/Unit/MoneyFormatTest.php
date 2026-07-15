<?php

use App\Support\Money;

it('formats money with spaced thousands and meaningful decimals only', function (mixed $amount, string $expected) {
    expect(Money::format($amount))->toBe($expected);
})->with([
    'whole millions stay clean' => [15_000_000, '15 000 000'],
    'cents are kept when present' => [16595.41, '16 595,41'],
    'string decimals with zero cents drop the tail' => ['64000.00', '64 000'],
    'null reads as zero' => [null, '0'],
    'halves keep their fraction' => [76.5, '76,50'],
]);
