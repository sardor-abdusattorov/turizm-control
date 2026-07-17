<?php

namespace App\Enums\Concerns;

/**
 * Builds the value => label() map for select filters and pickers, shared by
 * the string-backed enums whose options() is just their cases in
 * declaration order.
 */
trait HasOptions
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static function (array $carry, self $case): array {
                $carry[$case->value] = $case->label();

                return $carry;
            },
            [],
        );
    }
}
