<?php

namespace App\Filament\Widgets\Dashboard;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class RecentActivityWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 5;

    public function getTableHeading(): string
    {
        return __('app.dashboard.recent_activity');
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Activity::query()->with('causer')->latest('id'))
            ->queryStringIdentifier('recentActivity')
            ->columns([
                TextColumn::make('event')
                    ->label(__('app.label.status'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state, Activity $record): string => $state ?: $record->log_name ?: '—'),

                TextColumn::make('description')
                    ->label(__('app.label.description'))
                    ->limit(60)
                    ->wrap(),

                TextColumn::make('causer.name')
                    ->label(__('app.label.user'))
                    ->placeholder(__('app.label.system'))
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('app.label.created_at'))
                    ->since()
                    ->tooltip(fn (Activity $record): ?string => $record->created_at?->format('d.m.Y H:i')),
            ])
            ->emptyStateHeading(__('app.label.no_history'))
            ->emptyStateIcon('heroicon-o-clock');
    }
}
