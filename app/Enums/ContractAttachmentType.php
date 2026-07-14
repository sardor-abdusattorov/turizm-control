<?php

namespace App\Enums;

/**
 * Papers that make up a real contract dossier, straight from the scanned
 * 2025 packages: the signed contract, the buyruq it rests on, competitors'
 * proposals, stand sketches, the invoice, the SWIFT slip, the acceptance act
 * and the bank-fee statement.
 */
enum ContractAttachmentType: string
{
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

    /**
     * value => label map for select filters and pickers.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static function (array $carry, self $type): array {
                $carry[$type->value] = $type->label();

                return $carry;
            },
            [],
        );
    }
}
