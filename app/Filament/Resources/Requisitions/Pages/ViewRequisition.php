<?php

namespace App\Filament\Resources\Requisitions\Pages;

use App\Enums\ApprovalStatus;
use App\Enums\RequisitionStatus;
use App\Filament\Resources\Requisitions\RequisitionResource;
use App\Filament\Support\ApprovalActions;
use App\Models\Requisition;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRequisition extends ViewRecord
{
    protected static string $resource = RequisitionResource::class;

    protected string $view = 'filament.resources.requisitions.pages.view-requisition';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->loadMissing(['approvals.user.department', 'approvals.user.position', 'author', 'project']);
    }

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
            ...ApprovalActions::make(),

            EditAction::make()
                ->visible(fn (Requisition $record): bool => $record->canBeEditedBy()),

            DeleteAction::make()
                ->visible(fn (Requisition $record): bool => $record->canBeEditedBy()),
        ];
    }

    /**
     * The reason the last round came back — the first thing the author needs
     * to see on a rejected requisition.
     */
    public function rejectionReason(): ?string
    {
        if ($this->record->status !== RequisitionStatus::Rejected) {
            return null;
        }

        return $this->record->approvals()
            ->where('status', ApprovalStatus::Rejected)
            ->orderByDesc('acted_at')
            ->value('comment');
    }
}
