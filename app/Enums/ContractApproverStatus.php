<?php

namespace App\Enums;

enum ContractApproverStatus: string
{
    case Queued = 'queued';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Skipped = 'skipped';
    case Invalidated = 'invalidated';

    public function label(): string
    {
        return __('app.contract_approver.status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued, self::Skipped, self::Invalidated => 'gray',
            self::Pending => 'primary',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}
