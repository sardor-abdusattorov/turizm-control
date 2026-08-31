<?php

namespace App\Filament\Resources\Requisitions\Pages;

use App\Filament\Resources\Requisitions\RequisitionResource;
use App\Models\Requisition;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRequisition extends EditRecord
{
    protected static string $resource = RequisitionResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // A requisition under review or already settled is a fixed statement —
        // the author edits it again only after it comes back rejected.
        abort_unless($this->record->canBeEditedBy(), 403);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (Requisition $record): bool => $record->canBeEditedBy()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return RequisitionResource::getUrl('view', ['record' => $this->record]);
    }
}
