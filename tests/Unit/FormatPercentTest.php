<?php

it('formats a percent compactly, trimming trailing zeros and a dangling point', function (mixed $input, string $expected) {
    expect(format_percent($input))->toBe($expected);
})->with([
    'one decimal kept' => [12.5, '12.5'],
    'trailing zero trimmed' => [12.50, '12.5'],
    'whole number, point trimmed' => [100, '100'],
    'whole float, point trimmed' => [100.00, '100'],
    'zero' => [0, '0'],
    'two decimals kept' => [33.33, '33.33'],
    'rounded to two decimals' => [33.333, '33.33'],
    'numeric string' => ['50', '50'],
    'small fraction' => [0.1, '0.1'],
]);
