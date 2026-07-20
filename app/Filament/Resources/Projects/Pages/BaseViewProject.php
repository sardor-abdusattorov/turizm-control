<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Support\MediaGalleryUpload;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

abstract class BaseViewProject extends ViewRecord
{
    protected string $view = 'filament.resources.projects.pages.view-project';

    public function getHeading(): string
    {
        return (string) $this->record->name;
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    /**
     * Add photos/videos straight from the Gallery tab — the same native
     * FileUpload the edit form uses, appending to the existing set instead
     * of round-tripping through the whole project form.
     */
    public function uploadGalleryAction(): Action
    {
        return Action::make('uploadGallery')
            ->label(__('app.action.upload_files'))
            ->icon('heroicon-o-photo')
            ->visible(fn (): bool => (bool) auth()->user()?->can('update', $this->record))
            ->modalHeading(__('app.label.gallery'))
            ->schema([
                MediaGalleryUpload::make('projects')
                    ->hiddenLabel()
                    ->required(),
            ])
            ->action(function (array $data): void {
                $fresh = array_values((array) ($data['gallery'] ?? []));

                $this->record->update([
                    'gallery' => array_values(array_unique([
                        ...($this->record->gallery ?? []),
                        ...$fresh,
                    ])),
                ]);

                Notification::make()->title(__('app.message.attachments_uploaded'))->success()->send();
            });
    }
}
