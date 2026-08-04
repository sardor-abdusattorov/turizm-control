<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * What a finished press tour leaves behind. Media coverage is the point of
 * the whole exercise, so it gets its own kind rather than living in «other».
 */
enum PressTourAttachmentType: string
{
    use HasOptions;

    case Report = 'report';
    case MediaCoverage = 'media_coverage';
    case Photo = 'photo';
    case Programme = 'programme';
    case Participants = 'participants';
    case Act = 'act';
    case Other = 'other';

    public function label(): string
    {
        return __('app.press_tour.attachment.'.$this->value);
    }
}
