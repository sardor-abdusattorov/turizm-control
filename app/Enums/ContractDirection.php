<?php

namespace App\Enums;

enum ContractDirection: string
{
    case Expense = 'expense';
    case Income = 'income';

    public function label(): string
    {
        return __('app.contract.direction.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Expense => 'info',
            self::Income => 'warning',
        };
    }

    /**
     * value => label map for select filters and pickers.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static function (array $carry, self $direction): array {
                $carry[$direction->value] = $direction->label();

                return $carry;
            },
            [],
        );
    }
}
