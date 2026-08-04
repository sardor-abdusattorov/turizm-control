<?php

namespace App\Filament\Resources\PressTours\Widgets;

use App\Enums\PressTourAttachmentType;
use App\Models\PressTour;
use App\Models\PressTourAttachment;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * The report pack a finished tour left behind, as a stock Filament table —
 * upload in the header, download/delete per row. Embedded on the press-tour
 * view page's Documents tab, mirroring the contract dossier widget.
 */
class PressTourDocumentsTableWidget extends TableWidget
{
    public int $pressTourId;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            // No heading: the tab label already names it.
            ->heading(null)
            ->query(fn (): Builder => PressTourAttachment::query()
                ->where('press_tour_id', $this->pressTourId)
                ->with('uploader')
                ->orderBy('sort'))
            ->columns([
                TextColumn::make('original_name')
                    ->label(__('app.label.file'))
                    ->icon('heroicon-o-document-text')
                    ->weight('semibold')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('type')
                    ->label(__('app.label.attachment_type'))
                    ->badge()
                    ->formatStateUsing(fn (?PressTourAttachmentType $state): ?string => $state?->label())
                    ->color('gray')
                    ->placeholder(__('app.label.not_set')),

                TextColumn::make('size')
                    ->label(__('app.label.size'))
                    ->state(fn (PressTourAttachment $record): string => $record->sizeLabel())
                    ->alignEnd(),

                TextColumn::make('uploader.name')
                    ->label(__('app.label.uploaded_by'))
                    ->placeholder(__('app.label.system')),

                TextColumn::make('created_at')
                    ->label(__('app.label.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                $this->uploadAction(),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('app.action.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (PressTourAttachment $record): ?string => $record->url(), shouldOpenInNewTab: true)
                    ->visible(fn (PressTourAttachment $record): bool => $record->url() !== null),

                Action::make('delete')
                    ->label(__('app.action.delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => $this->canManage())
                    ->action(function (PressTourAttachment $record): void {
                        $record->delete();

                        Notification::make()->title(__('app.message.attachment_deleted'))->success()->send();
                    }),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('app.message.no_attachments'))
            ->emptyStateIcon('heroicon-o-paper-clip');
    }

    private function uploadAction(): Action
    {
        return Action::make('uploadTourDocuments')
            ->label(__('app.action.upload_files'))
            ->icon('heroicon-o-paper-clip')
            ->visible(fn (): bool => $this->canManage())
            ->modalHeading(__('app.action.upload_files'))
            ->schema([
                Select::make('type')
                    ->label(__('app.label.attachment_type'))
                    ->options(PressTourAttachmentType::options())
                    ->default(PressTourAttachmentType::Report->value)
                    ->selectablePlaceholder(false),

                FileUpload::make('files')
                    ->hiddenLabel()
                    ->helperText(__('app.helper.press_tour_documents'))
                    ->multiple()
                    ->required()
                    ->disk('local')
                    ->directory(fn (): string => 'uploads/files/press-tours/'.now()->format('Y/m'))
                    ->visibility('private')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'image/jpeg',
                        'image/png',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->maxSize(25600)
                    ->storeFileNamesIn('original_names'),
            ])
            ->action(function (array $data): void {
                $tour = $this->pressTour();
                $names = (array) ($data['original_names'] ?? []);
                $sort = (int) $tour->attachments()->max('sort');

                foreach ((array) ($data['files'] ?? []) as $key => $path) {
                    $tour->attachments()->create([
                        'type' => $data['type'] ?? null,
                        'file_path' => $path,
                        // Filament keys the stored names by the stored PATH,
                        // not by the upload uuid.
                        'original_name' => $names[$path] ?? $names[$key] ?? basename((string) $path),
                        'size' => Storage::disk('local')->exists($path) ? Storage::disk('local')->size($path) : 0,
                        'uploaded_by' => auth()->id(),
                        'sort' => ++$sort,
                    ]);
                }

                Notification::make()->title(__('app.message.attachments_uploaded'))->success()->send();
            });
    }

    /**
     * Filing the report pack is part of editing the tour, so it follows the
     * same permission rather than inventing one.
     */
    private function canManage(): bool
    {
        return Gate::allows('update', $this->pressTour());
    }

    private function pressTour(): PressTour
    {
        return PressTour::query()->findOrFail($this->pressTourId);
    }
}
