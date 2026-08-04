<?php

namespace App\Filament\Resources\PressTours\Pages;

use App\Filament\Resources\PressTours\Pages\Concerns\HandlesTourDocuments;
use App\Filament\Resources\PressTours\PressTourResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPressTour extends EditRecord
{
    use HandlesTourDocuments;

    protected static string $resource = PressTourResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Prefill the upload field with the stored pack, so the files show up as
     * chips and can be removed right here.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $attachments = $this->record->attachments()->get();

        $data['document_files'] = $attachments->pluck('file_path')->all();
        $data['document_names'] = $attachments->pluck('original_name', 'file_path')->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->extractDocumentUploads($data);
    }

    protected function afterSave(): void
    {
        $this->storeTourDocuments();
    }

    protected function getRedirectUrl(): string
    {
        return PressTourResource::getUrl('view', ['record' => $this->record]);
    }
}
