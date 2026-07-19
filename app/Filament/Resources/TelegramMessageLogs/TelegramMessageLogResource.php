<?php

namespace App\Filament\Resources\TelegramMessageLogs;

use App\Filament\Resources\TelegramMessageLogs\Pages\ListTelegramMessageLogs;
use App\Models\TelegramMessageLog;
use App\Services\Telegram\TelegramService;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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
                    ->formatStateUsing(fn (string $state): string => $state === 'sendMessage'
                        ? __('app.label.telegram_log_sent')
                        : __('app.label.telegram_log_edited'))
                    ->icon(fn (string $state): string => $state === 'sendMessage'
                        ? 'heroicon-m-paper-airplane'
                        : 'heroicon-m-pencil-square')
                    ->color(fn (string $state): string => $state === 'sendMessage' ? 'info' : 'gray'),

                TextColumn::make('telegramUser.user.name')
                    ->label(__('app.label.recipient'))
                    ->description(fn (TelegramMessageLog $record): ?string => $record->telegramUser?->user?->name
                        ? $record->chat_id
                        : null)
                    ->placeholder(fn (TelegramMessageLog $record): string => $record->chat_id ?? __('app.label.not_set'))
                    ->searchable(),

                TextColumn::make('cleanText')
                    ->label(__('app.label.message'))
                    ->placeholder(__('app.label.not_set'))
                    ->limit(60)
                    ->wrap()
                    ->tooltip(fn (TelegramMessageLog $record): ?string => $record->cleanText),

                TextColumn::make('humanError')
                    ->label(__('app.label.error'))
                    ->placeholder(__('app.label.not_set'))
                    ->badge()
                    ->color('danger')
                    ->limit(60)
                    ->wrap()
                    ->tooltip(fn (TelegramMessageLog $record): ?string => $record->humanError),
            ])
            ->filters([
                TernaryFilter::make('ok')
                    ->label(__('app.label.status')),

                SelectFilter::make('method')
                    ->label(__('app.label.method'))
                    ->options([
                        'sendMessage' => __('app.label.telegram_log_sent'),
                        'editMessageText' => __('app.label.telegram_log_edited'),
                    ]),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(fn (TelegramMessageLog $record): string => '№ '.$record->getKey())
                    ->schema([
                        Section::make()
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label(__('app.label.created_at'))
                                            ->dateTime('d.m.Y H:i:s'),

                                        IconEntry::make('ok')
                                            ->label(__('app.label.status'))
                                            ->boolean(),

                                        TextEntry::make('method')
                                            ->label(__('app.label.method'))
                                            ->badge()
                                            ->formatStateUsing(fn (string $state): string => $state === 'sendMessage'
                                                ? __('app.label.telegram_log_sent')
                                                : __('app.label.telegram_log_edited'))
                                            ->color(fn (string $state): string => $state === 'sendMessage' ? 'info' : 'gray'),

                                        TextEntry::make('status')
                                            ->label(__('app.label.telegram_log_http'))
                                            ->placeholder(__('app.label.not_set')),

                                        TextEntry::make('telegramUser.user.name')
                                            ->label(__('app.label.recipient'))
                                            ->placeholder(__('app.label.not_set')),

                                        TextEntry::make('chat_id')
                                            ->label('chat_id')
                                            ->copyable()
                                            ->fontFamily('mono')
                                            ->placeholder(__('app.label.not_set')),
                                    ]),

                                TextEntry::make('cleanText')
                                    ->label(__('app.label.message'))
                                    ->placeholder(__('app.label.not_set'))
                                    ->prose()
                                    ->columnSpanFull(),

                                TextEntry::make('humanError')
                                    ->label(__('app.label.error'))
                                    ->color('danger')
                                    ->columnSpanFull()
                                    ->visible(fn (TelegramMessageLog $record): bool => (bool) $record->error),
                            ]),
                    ]),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelegramMessageLogs::route('/'),
        ];
    }
}
