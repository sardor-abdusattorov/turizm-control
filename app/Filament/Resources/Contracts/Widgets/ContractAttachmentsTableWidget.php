<?php

namespace App\Filament\Resources\Contracts\Widgets;

use App\Enums\ContractAttachmentType;
use App\Models\Contract;
use App\Models\ContractAttachment;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * The contract dossier as a stock Filament table — upload in the header,
 * open/download/delete per row. Embedded on the contract view page's
 * Attachments tab; management follows Contract::attachmentsManageableBy()
 * (frozen mid-approval, open before and after).
 */
class ContractAttachmentsTableWidget extends TableWidget
{
    public int $contractId;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('app.label.attachments'))
            ->query(fn (): Builder => ContractAttachment::query()
                ->where('contract_id', $this->contractId)
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
                    ->formatStateUsing(fn (ContractAttachmentType $state): string => $state->label())
                    ->color('gray')
                    ->placeholder(__('app.label.not_set')),

                TextColumn::make('size')
                    ->label(__('app.label.size'))
                    ->state(fn (ContractAttachment $record): string => $record->sizeLabel())
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
                Action::make('open')
                    ->label(__('app.action.open'))
                    ->icon('heroicon-o-eye')
                    ->url(fn (ContractAttachment $record): ?string => $this->openUrl($record), shouldOpenInNewTab: true)
                    ->visible(fn (ContractAttachment $record): bool => $this->openUrl($record) !== null),

                Action::make('download')
                    ->label(__('app.action.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (ContractAttachment $record): ?string => $record->url(), shouldOpenInNewTab: true)
                    ->visible(fn (ContractAttachment $record): bool => $record->url() !== null),

                Action::make('delete')
                    ->label(__('app.action.delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => $this->contract()->attachmentsManageableBy())
                    ->action(function (ContractAttachment $record): void {
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
        return Action::make('uploadAttachments')
            ->label(__('app.action.upload_files'))
            ->icon('heroicon-o-paper-clip')
            ->visible(fn (): bool => $this->contract()->attachmentsManageableBy())
            ->modalHeading(__('app.action.upload_files'))
            ->schema([
                FileUpload::make('files')
                    ->hiddenLabel()
                    ->helperText(__('app.helper.attachment_scans'))
                    ->multiple()
                    ->required()
                    ->disk('local')
                    ->directory(fn (): string => 'uploads/files/contract-attachments/'.now()->format('Y/m'))
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                    ->maxSize(25600)
                    ->storeFileNamesIn('original_names'),
            ])
            ->action(function (array $data): void {
                $contract = $this->contract();
                $names = (array) ($data['original_names'] ?? []);
                $sort = (int) $contract->attachments()->max('sort');

                foreach ((array) ($data['files'] ?? []) as $key => $path) {
                    $contract->attachments()->create([
                        'file_path' => $path,
                        // Filament keys the stored names by the stored PATH,
                        // not by the upload uuid — $key alone always missed.
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
     * The in-browser or OnlyOffice view link — PDFs and images open in the
     * browser's own viewer, Word files in the read-only OnlyOffice viewer.
     */
    private function openUrl(ContractAttachment $attachment): ?string
    {
        if (! $attachment->fileExists()) {
            return null;
        }

        if ($attachment->isWordDocument()) {
            return route('contracts.attachments.viewer', ['contract' => $this->contractId, 'attachment' => $attachment]);
        }

        if ($attachment->isBrowserViewable()) {
            return route('contracts.attachments.inline', ['contract' => $this->contractId, 'attachment' => $attachment]);
        }

        return null;
    }

    private function contract(): Contract
    {
        return Contract::query()->findOrFail($this->contractId);
    }
}
