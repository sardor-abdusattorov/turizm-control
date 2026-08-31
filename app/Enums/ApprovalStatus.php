<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ApprovalStatus: string
{
    use HasOptions;

    case Queued = 'queued';

    case Pending = 'pending';

    case Approved = 'approved';

    case Rejected = 'rejected';

    case Invalidated = 'invalidated';

    public function label(): string
    {
        return __('app.approval.status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued, self::Invalidated => 'gray',
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Queued => 'heroicon-o-ellipsis-horizontal-circle',
            self::Pending => 'heroicon-o-clock',
            self::Approved => 'heroicon-o-check-circle',
            self::Rejected => 'heroicon-o-x-circle',
            self::Invalidated => 'heroicon-o-arrow-path',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected], true);
    }
}
