<?php

namespace App\Filament\Resources\Requisitions\Pages;

use App\Filament\Resources\Requisitions\RequisitionResource;
use App\Filament\Resources\Requisitions\Schemas\RequisitionInfolist;
use App\Models\Requisition;
use App\Services\Requisitions\RequisitionWorkflow;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewRequisition extends ViewRecord
{
    protected static string $resource = RequisitionResource::class;

    public function getHeading(): string
    {
        return $this->record->number;
    }

    public function getSubheading(): ?string
    {
        return $this->record->title;
    }

    public function infolist(Schema $schema): Schema
    {
        return RequisitionInfolist::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submitForReview')
                ->label(__('app.action.send_for_review'))
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading(__('app.action.send_for_review'))
                ->modalDescription(fn (): string => __('app.message.send_for_review_confirm', [
                    'days' => Requisition::reviewDays(),
                ]))
                ->visible(fn (): bool => $this->record->canBeSubmittedBy())
                ->action(fn (RequisitionWorkflow $workflow) => $this->settle(
                    $workflow->submit($this->record),
                    __('app.message.sent_for_review'),
                )),

            Action::make('approveRequisition')
                ->label(__('app.action.approve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->modalHeading(__('app.action.approve'))
                ->schema([
                    Textarea::make('comment')
                        ->label(__('app.label.review_comment'))
                        ->rows(3),
                ])
                ->visible(fn (): bool => $this->record->canBeReviewedBy())
                ->action(fn (array $data, RequisitionWorkflow $workflow) => $this->settle(
                    $workflow->approve($this->record, $data['comment'] ?? null),
                    __('app.message.requisition_approved'),
                )),

            Action::make('rejectRequisition')
                ->label(__('app.action.reject'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->modalHeading(__('app.action.reject'))
                ->schema([
                    Textarea::make('comment')
                        ->label(__('app.label.review_comment'))
                        ->helperText(__('app.helper.reject_comment'))
                        ->rows(3)
                        ->required(),
                ])
                ->visible(fn (): bool => $this->record->canBeReviewedBy())
                ->action(fn (array $data, RequisitionWorkflow $workflow) => $this->settle(
                    $workflow->reject($this->record, $data['comment']),
                    __('app.message.requisition_rejected'),
                )),

            EditAction::make()
                ->visible(fn (): bool => $this->record->canBeEditedBy()),
        ];
    }

    private function settle(bool $succeeded, string $message): void
    {
        if (! $succeeded) {
            Notification::make()->title(__('app.message.action_not_allowed'))->danger()->send();

            return;
        }

        $this->record->refresh();

        Notification::make()->title($message)->success()->send();
    }
}
