<?php

namespace App\Filament\Pages;

use App\Models\TelegramUser;
use App\Services\Telegram\TelegramBroadcaster;
use App\Services\Telegram\TelegramService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class TelegramBroadcast extends Page
{
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

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('super_admin')
            && app(TelegramService::class)->isConfigured();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function connectedCount(): int
    {
        return TelegramUser::query()->whereNotNull('chat_id')->count();
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
