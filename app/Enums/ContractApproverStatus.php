<?php

namespace App\Enums;

/**
 * Per-approver state within a contract's approval chain. String-backed for a
 * readable database; the enum centralises each status' label and pill colour.
 */
enum ContractApproverStatus: string
{
    case Queued = 'queued';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Skipped = 'skipped';
    case Invalidated = 'invalidated';

    public function label(): string
    {
        return __('app.contract_approver.status.'.$this->value);
    }

    /** Filament colour token used for the status pill. */
    public function color(): string
    {
        return match ($this) {
            self::Queued, self::Skipped, self::Invalidated => 'gray',
            self::Pending => 'primary',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Returned => 'info',
        };
    }
}
