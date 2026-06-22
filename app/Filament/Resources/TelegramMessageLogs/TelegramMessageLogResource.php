<?php

namespace App\Filament\Resources\TelegramMessageLogs;

use App\Filament\Resources\TelegramMessageLogs\Pages\ListTelegramMessageLogs;
use App\Models\TelegramMessageLog;
use App\Services\Telegram\TelegramService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TelegramMessageLogResource extends Resource
{
    protected static ?string $model = TelegramMessageLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    public static function getNavigationGroup(): ?string
    {
        return __('app.label.administration');
    }

    public static function getNavigationSort(): int
    {
        return 7;
    }

    public static function getModelLabel(): string
    {
        return __('app.label.telegram_log_single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.label.telegram_log_plural');
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('super_admin')
            && app(TelegramService::class)->isConfigured();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('telegramUser.user'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('app.label.created_at'))
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),

                IconColumn::make('ok')
                    ->label(__('app.label.status'))
                    ->boolean(),

                TextColumn::make('method')
                    ->label(__('app.label.method'))
                    ->badge()
                    ->color(fn (string $state): string => $state === 'sendMessage' ? 'info' : 'gray'),

                TextColumn::make('telegramUser.user.name')
                    ->label(__('app.label.recipient'))
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('chat_id')
                    ->label('chat_id')
                    ->toggleable()
                    ->copyable()
                    ->fontFamily('mono'),

                TextColumn::make('text')
                    ->label(__('app.label.message'))
                    ->formatStateUsing(fn (?string $state): string => trim(preg_replace('/<[^>]+>/', '', (string) $state)))
                    ->limit(70)
                    ->wrap()
                    ->tooltip(fn (TelegramMessageLog $record): ?string => $record->text
                        ? trim(preg_replace('/<[^>]+>/', '', $record->text))
                        : null),

                TextColumn::make('error')
                    ->label(__('app.label.error'))
                    ->placeholder('—')
                    ->limit(40)
                    ->color('danger')
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('ok')
                    ->label(__('app.label.status')),

                SelectFilter::make('method')
                    ->label(__('app.label.method'))
                    ->options([
                        'sendMessage' => 'sendMessage',
                        'editMessageText' => 'editMessageText',
                    ]),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelegramMessageLogs::route('/'),
        ];
    }
}
