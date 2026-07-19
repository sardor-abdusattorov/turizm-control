<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ContractDirection: string
{
    use HasOptions;

    case Expense = 'expense';
    case Income = 'income';

    public function label(): string
    {
        return __('app.contract.direction.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Expense => 'gray',
            self::Income => 'info',
        };
    }
}
