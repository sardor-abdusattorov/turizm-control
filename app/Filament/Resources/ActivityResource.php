<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityResource\Pages\ListActivities;
use MrAdder\FilamentLogger\Resources\ActivityResource as BaseActivityResource;
use MrAdder\FilamentLogger\Resources\ActivityResource\Pages\ViewActivity;

class ActivityResource extends BaseActivityResource
{
    public static function getNavigationGroup(): ?string
    {
        return __('app.label.administration');
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function getNavigationBadge(): ?string
    {
        return number_format(static::getModel()::count());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
            'view' => ViewActivity::route('/{record}'),
        ];
    }
}
