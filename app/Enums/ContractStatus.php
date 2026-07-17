<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ContractStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case InReview = 'in_review';
    case PendingDirector = 'pending_director';
    case InReviewDirector = 'in_review_director';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('app.contract.status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::InReview, self::InReviewDirector => 'primary',
            self::PendingDirector => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}
