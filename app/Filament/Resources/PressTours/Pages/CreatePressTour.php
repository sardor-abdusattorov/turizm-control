<?php

namespace App\Filament\Resources\PressTours\Pages;

use App\Filament\Resources\PressTours\Pages\Concerns\HandlesTourDocuments;
use App\Filament\Resources\PressTours\PressTourResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePressTour extends CreateRecord
{
    use HandlesTourDocuments;

    protected static string $resource = PressTourResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] ??= auth()->id();

        return $this->extractDocumentUploads($data);
    }

    protected function afterCreate(): void
    {
        $this->storeTourDocuments();
    }

    protected function getRedirectUrl(): string
    {
        return PressTourResource::getUrl('view', ['record' => $this->record]);
    }
}
