<?php

namespace App\Enums\Concerns;

trait HasOptions
{
    /** @return array<string, string> */
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
