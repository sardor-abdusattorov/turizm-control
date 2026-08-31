<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Where a requisition stands: written by its author, sitting with the supply
 * officer, then settled either way. A rejected one goes back to draft when the
 * author picks it up again.
 */
enum RequisitionStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('app.requisition.status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::InReview => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'heroicon-o-pencil-square',
            self::InReview => 'heroicon-o-clock',
            self::Approved => 'heroicon-o-check-circle',
            self::Rejected => 'heroicon-o-x-circle',
        };
    }
}
