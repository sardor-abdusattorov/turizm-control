<?php

namespace App\Filament\Resources\Requisitions\Pages;

use App\Filament\Resources\Requisitions\Pages\Concerns\HandlesApprovalChain;
use App\Filament\Resources\Requisitions\RequisitionResource;
use App\Models\Requisition;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateRequisition extends CreateRecord
{
    use HandlesApprovalChain;

    protected static string $resource = RequisitionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['author_id'] = Auth::id();
        $data['number'] = Requisition::nextNumber();

        return $this->captureApprovers($data);
    }

    protected function afterCreate(): void
    {
        $this->syncChain();
    }

    protected function getRedirectUrl(): string
    {
        return RequisitionResource::getUrl('view', ['record' => $this->record]);
    }
}
