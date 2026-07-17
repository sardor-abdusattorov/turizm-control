<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Models\Contact;
use App\Models\Sponsor;

/**
 * Which party a contract kind is signed with. Expense and participant-fee
 * contracts face a {@see Contact} (supplier / tour operator);
 * sponsorship contracts face a {@see Sponsor}. The kind lives on
 * the ContractType so the contract form knows which counterparty picker to
 * show, and project income can split «Участники» from «Спонсоры».
 */
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
