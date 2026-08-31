<?php

namespace App\Filament\Resources\Requisitions\Pages;

use App\Filament\Resources\Requisitions\Pages\Concerns\HandlesApprovalChain;
use App\Filament\Resources\Requisitions\RequisitionResource;
use App\Models\Requisition;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRequisition extends EditRecord
{
    use HandlesApprovalChain;

    protected static string $resource = RequisitionResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_unless($this->record->canBeEditedBy(), 403);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (Requisition $record): bool => $record->canBeEditedBy()),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->captureApprovers($data);
    }

    protected function afterSave(): void
    {
        $this->settleRound();
    }

    protected function getRedirectUrl(): string
    {
        return RequisitionResource::getUrl('view', ['record' => $this->record]);
    }
}
