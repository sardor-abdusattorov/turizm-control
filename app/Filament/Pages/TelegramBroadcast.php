<?php

namespace App\Filament\Pages;

use App\Models\TelegramUser;
use App\Services\Telegram\TelegramBroadcaster;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class TelegramBroadcast extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected string $view = 'filament.pages.telegram-broadcast';

    protected static ?string $slug = 'telegram-broadcast';

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-megaphone';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('app.label.administration');
    }

    public static function getNavigationSort(): int
    {
        return 6;
    }

    public static function getNavigationLabel(): string
    {
        return __('app.label.telegram_broadcast');
    }

    public function getTitle(): string
    {
        return __('app.label.telegram_broadcast');
    }

    public function getSubheading(): ?string
    {
        return __('app.label.telegram_broadcast_sub');
    }

    public function connectedCount(): int
    {
        return TelegramUser::query()->whereNotNull('chat_id')->count();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                TelegramUser::query()
                    ->whereNotNull('chat_id')
                    ->with('user.department'),
            )
            ->heading(__('app.label.connected_accounts'))
            ->defaultSort('linked_at', 'desc')
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('app.label.employee'))
                    ->weight('semibold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('username')
                    ->label(__('app.label.telegram_handle'))
                    ->formatStateUsing(fn (?string $state): string => $state ? '@'.$state : '—')
                    ->color('primary'),

                TextColumn::make('user.department.name')
                    ->label(__('app.label.department'))
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('locale')
                    ->label(__('app.label.language'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'uz' => '🇺🇿 '.__('app.label.uz'),
                        default => '🇷🇺 '.__('app.label.ru'),
                    }),

                TextColumn::make('linked_at')
                    ->label(__('app.label.connected_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('last_seen_at')
                    ->label(__('app.label.last_active'))
                    ->since()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->emptyStateHeading(__('app.label.broadcast_intro'))
            ->emptyStateIcon('heroicon-o-user-group');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('broadcast')
                ->label(__('app.action.send_broadcast'))
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->disabled(fn (): bool => $this->connectedCount() === 0)
                ->schema([
                    Textarea::make('message')
                        ->label(__('app.label.broadcast_message'))
                        ->helperText(__('app.label.broadcast_message_help'))
                        ->required()
                        ->rows(5)
                        ->maxLength(3500),
                ])
                ->requiresConfirmation()
                ->modalHeading(__('app.action.send_broadcast'))
                ->modalDescription(fn (): string => __('app.message.broadcast_confirm', [
                    'count' => $this->connectedCount(),
                ]))
                ->modalSubmitActionLabel(__('app.action.send_broadcast'))
                ->action(function (array $data): void {
                    $sent = app(TelegramBroadcaster::class)->send($data['message']);

                    Notification::make()
                        ->title(__('app.message.broadcast_sent', ['count' => $sent]))
                        ->success()
                        ->send();
                }),
        ];
    }
}
