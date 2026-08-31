<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

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
