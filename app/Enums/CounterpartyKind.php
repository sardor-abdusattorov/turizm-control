<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Models\Contact;
use App\Models\Sponsor;

enum CounterpartyKind: string
{
    use HasOptions;

    case Contact = 'contact';
    case Sponsor = 'sponsor';

    public function label(): string
    {
        return __('app.counterparty_kind.'.$this->value);
    }
}
