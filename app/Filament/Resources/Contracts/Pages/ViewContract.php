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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    protected string $view = 'filament.resources.contracts.pages.view-contract';

    public function getHeading(): string
    {
        return $this->record->number;
    }

    public function getSubheading(): ?string
    {
        return $this->record->title;
    }

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
     * Headline context for the hero: a status-aware one-liner plus the SLA
     * state of the step currently in review.
     *
     * @return array{message: string, overdue: bool, due: ?Carbon}
     */
    public function heroContext(): array
    {
        $contract = $this->record;
        $current = $contract->currentApprover();

        return match ($contract->status) {
            Contract::STATUS_DRAFT => [
                'message' => __('app.message.hero_draft'),
                'overdue' => false,
                'due' => null,
            ],
            Contract::STATUS_IN_REVIEW => [
                'message' => $current?->user
                    ? __('app.message.hero_in_review', ['name' => $current->user->name])
                    : __('app.message.hero_in_review_generic'),
                'overdue' => (bool) $current?->isOverdue(),
                'due' => $current?->due_at,
            ],
            Contract::STATUS_APPROVED => [
                'message' => $contract->signed_at
                    ? __('app.message.hero_approved', ['date' => $contract->signed_at->format('d.m.Y')])
                    : __('app.message.hero_approved_generic'),
                'overdue' => false,
                'due' => null,
            ],
            Contract::STATUS_REJECTED => [
                'message' => __('app.message.hero_rejected'),
                'overdue' => false,
                'due' => null,
            ],
            default => ['message' => '', 'overdue' => false, 'due' => null],
        };
    }

    /**
     * Coarse lifecycle state for a chain step, used to colour the timeline
     * rail and node: approved / rejected / returned / current / queued.
     */
    public function approverState(ContractApprover $approver): string
    {
        return match (true) {
            $approver->status === ContractApprover::STATUS_APPROVED => 'approved',
            $approver->status === ContractApprover::STATUS_REJECTED => 'rejected',
            $approver->status === ContractApprover::STATUS_RETURNED => 'returned',
            $this->isCurrentApprover($approver) => 'current',
            default => 'queued',
        };
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
            'Contract Document Saved', 'Contract Document Forcesave' => ['icon' => 'heroicon-o-document-text', 'color' => 'info'],
            'Contract Edit Invalidated' => ['icon' => 'heroicon-o-no-symbol', 'color' => 'warning'],
            'created' => ['icon' => 'heroicon-o-sparkles', 'color' => 'info'],
            'updated' => ['icon' => 'heroicon-o-pencil-square', 'color' => 'gray'],
            'deleted' => ['icon' => 'heroicon-o-trash', 'color' => 'danger'],
            default => ['icon' => 'heroicon-o-information-circle', 'color' => 'gray'],
        };
    }

    /**
     * Coarse event group used by the history filter chips:
     * workflow (submit/approve/reject/return) vs edit (created/updated/saved).
     */
    public function activityGroup(string $event): string
    {
        return match ($event) {
            'Contract Submitted', 'Contract Step Approved', 'Contract Approved',
            'Contract Rejected', 'Contract Returned' => 'workflow',
            default => 'edit',
        };
    }
}
