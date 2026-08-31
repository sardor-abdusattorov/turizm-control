<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ContractAttachmentType: string
{
    use HasOptions;

    case ContractScan = 'contract_scan';
    case OrderCopy = 'order_copy';
    case Proposal = 'proposal';
    case Sketch = 'sketch';
    case Invoice = 'invoice';
    case Swift = 'swift';
    case Act = 'act';
    case BankFees = 'bank_fees';
    case Other = 'other';

    public function label(): string
    {
        return __('app.contract.attachment.'.$this->value);
    }
}
