<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Services\Contracts\ContractWorkflow;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    protected string $view = 'filament.resources.contracts.pages.view-contract';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submitForApproval')
                ->label(__('app.action.submit_for_approval'))
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('app.action.submit_for_approval'))
                ->modalDescription(__('app.message.submit_for_approval_confirm'))
                ->visible(fn () => $this->record?->canBeSubmittedBy())
                ->action(function (ContractWorkflow $workflow): void {
                    if (! $workflow->submit($this->record)) {
                        Notification::make()->title(__('app.message.action_not_allowed'))->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('app.message.submitted_for_approval'))->success()->send();
                    $this->refreshFormData(['status']);
                }),

            ...EditContract::approvalActions($this->record),

            Action::make('downloadPdf')
                ->label(__('app.action.download_pdf'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn () => route('contracts.pdf.download', ['contract' => $this->record]))
                ->visible(fn () => $this->record?->status === Contract::STATUS_APPROVED),

            EditAction::make()
                ->icon('heroicon-o-pencil-square')
                ->visible(fn () => $this->record?->canBeEditedBy()),
        ];
    }

    /**
     * Activity-log trail for this contract — the "execution history".
     */
    public function getActivities(): Collection
    {
        return Activity::query()
            ->where('subject_type', $this->record->getMorphClass())
            ->where('subject_id', $this->record->getKey())
            ->with('causer')
            ->latest()
            ->limit(60)
            ->get();
    }

    public function documentSizeLabel(): ?string
    {
        if (! $this->record->documentExists()) {
            return null;
        }

        $bytes = Storage::disk('local')->size($this->record->documentPath());

        return number_format($bytes / 1024, 1).' KB';
    }

    public function pdfPreviewUrl(): ?string
    {
        if (! $this->record->documentExists()) {
            return null;
        }

        if (! in_array($this->record->status, [Contract::STATUS_IN_REVIEW, Contract::STATUS_APPROVED], true)) {
            return null;
        }

        return route('contracts.pdf.inline', ['contract' => $this->record]);
    }

    public function editorUrl(string $mode = 'view'): string
    {
        return route('contracts.editor', ['contract' => $this->record, 'mode' => $mode]);
    }

    public function approverAvatar(ContractApprover $approver): string
    {
        return $approver->user?->getFilamentAvatarUrl()
            ?? 'https://ui-avatars.com/api/?name='.urlencode($approver->user?->name ?? '?').'&background=E0E7FF&color=4338CA&size=80';
    }

    /**
     * @return array{icon: string, color: string}
     */
    public function approverVisual(ContractApprover $approver): array
    {
        if ($approver->status === ContractApprover::STATUS_APPROVED) {
            return ['icon' => 'heroicon-s-check-circle', 'color' => 'success'];
        }

        if ($approver->status === ContractApprover::STATUS_REJECTED) {
            return ['icon' => 'heroicon-s-x-circle', 'color' => 'danger'];
        }

        if ($approver->status === ContractApprover::STATUS_RETURNED) {
            return ['icon' => 'heroicon-s-arrow-uturn-left', 'color' => 'info'];
        }

        if ($this->isCurrentApprover($approver)) {
            return ['icon' => 'heroicon-s-clock', 'color' => 'warning'];
        }

        return ['icon' => 'heroicon-o-minus-circle', 'color' => 'gray'];
    }

    public function isCurrentApprover(ContractApprover $approver): bool
    {
        return $this->record->currentApprover()?->id === $approver->id;
    }

    /**
     * @return array{icon: string, color: string}
     */
    public function activityVisual(string $event): array
    {
        return match ($event) {
            'Contract Submitted' => ['icon' => 'heroicon-o-paper-airplane', 'color' => 'info'],
            'Contract Step Approved', 'Contract Approved' => ['icon' => 'heroicon-o-check-circle', 'color' => 'success'],
            'Contract Rejected' => ['icon' => 'heroicon-o-x-circle', 'color' => 'danger'],
            'Contract Returned' => ['icon' => 'heroicon-o-arrow-uturn-left', 'color' => 'warning'],
            'Contract Document Saved', 'Contract Document Forcesave' => ['icon' => 'heroicon-o-document-text', 'color' => 'gray'],
            default => ['icon' => 'heroicon-o-information-circle', 'color' => 'gray'],
        };
    }
}
