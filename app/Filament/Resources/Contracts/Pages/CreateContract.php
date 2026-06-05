<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Pages\ContractDocumentEditor;
use App\Filament\Resources\Contracts\ContractResource;
use App\Services\Documents\ContractPlaceholderValues;
use App\Services\Documents\TemplateFiller;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateContract extends CreateRecord
{
    protected static string $resource = ContractResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['responsible_id'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->buildDocumentFromTemplate(
            app(TemplateFiller::class),
            app(ContractPlaceholderValues::class),
        );
    }

    protected function getRedirectUrl(): string
    {
        return ContractDocumentEditor::getUrl(['record' => $this->record]);
    }
}
