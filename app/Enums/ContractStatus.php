<?php

namespace App\Enums;

enum ContractStatus: string
{
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

    /**
     * value => label map for select filters and pickers.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static function (array $carry, self $status): array {
                $carry[$status->value] = $status->label();

                return $carry;
            },
            [],
        );
    }
}
