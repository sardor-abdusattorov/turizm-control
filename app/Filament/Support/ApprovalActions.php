<?php

namespace App\Filament\Support;

use App\Models\Requisition;
use App\Models\User;
use App\Services\Approvals\ApprovalWorkflow;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Throwable;

class ApprovalActions
{
    /** @return array<int, Action> */
    public static function make(): array
    {
        return [
            static::submit(),
            static::approve(),
            static::reject(),
            static::recall(),
            static::returnToWork(),
        ];
    }

    public static function returnToWork(): Action
    {
        return Action::make('returnToWork')
            ->label(__('app.approval.action.return_to_work'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(__('app.approval.action.return_to_work'))
            ->modalDescription(__('app.approval.confirm.return_to_work'))
            ->visible(fn (Requisition $record): bool => $record->canBeReturnedToWorkBy())
            ->action(fn (Requisition $record) => static::run(
                fn () => app(ApprovalWorkflow::class)->returnToWork($record),
                __('app.approval.message.returned_to_work'),
            ));
    }

    public static function submit(): Action
    {
        return Action::make('submitForApproval')
            ->label(__('app.approval.action.submit'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading(__('app.approval.action.submit'))
            ->modalDescription(fn (): string => __('app.approval.confirm.submit', [
                'days' => Requisition::reviewDays(),
            ]))
            ->visible(fn (Requisition $record): bool => $record->canBeSubmittedBy())
            ->action(fn (Requisition $record) => static::run(
                fn () => app(ApprovalWorkflow::class)->submit($record),
                __('app.approval.message.submitted'),
            ));
    }

    public static function approve(): Action
    {
        return Action::make('approve')
            ->label(__('app.approval.action.approve'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->schema([
                Textarea::make('comment')
                    ->label(__('app.approval.field.comment'))
                    ->rows(3),
            ])
            ->visible(fn (Requisition $record): bool => $record->awaitsApprovalFrom(static::actor()))
            ->action(fn (Requisition $record, array $data) => static::run(
                fn () => app(ApprovalWorkflow::class)->approve($record, static::actor(), $data['comment'] ?? null),
                __('app.approval.message.approved'),
            ));
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label(__('app.approval.action.reject'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->schema([
                Textarea::make('comment')
                    ->label(__('app.approval.field.reason'))
                    ->helperText(__('app.approval.field.reason_help'))
                    ->required()
                    ->rows(3),
            ])
            ->visible(fn (Requisition $record): bool => $record->acceptsRejectionFrom(static::actor()))
            ->action(fn (Requisition $record, array $data) => static::run(
                fn () => app(ApprovalWorkflow::class)->reject($record, static::actor(), $data['comment']),
                __('app.approval.message.rejected'),
            ));
    }

    public static function recall(): Action
    {
        return Action::make('recall')
            ->label(__('app.approval.action.recall'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('app.approval.action.recall'))
            ->modalDescription(__('app.approval.confirm.recall'))
            ->visible(fn (Requisition $record): bool => $record->canBeRecalledBy())
            ->action(fn (Requisition $record) => static::run(
                fn () => app(ApprovalWorkflow::class)->recall($record),
                __('app.approval.message.recalled'),
            ));
    }

    protected static function actor(): ?User
    {
        return auth()->user();
    }

    protected static function run(callable $callback, string $success): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($success)->success()->send();
    }
}
