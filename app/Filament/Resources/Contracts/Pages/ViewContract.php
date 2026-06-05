<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label(__('app.action.download_pdf'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn () => route('contracts.pdf.download', ['contract' => $this->record]))
                ->visible(fn () => $this->record?->status === Contract::STATUS_APPROVED),

            Action::make('previewPdf')
                ->label(__('app.action.preview_pdf'))
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(
                    fn () => route('contracts.pdf.inline', ['contract' => $this->record]),
                    shouldOpenInNewTab: true,
                )
                ->visible(fn () => $this->record?->documentExists() && in_array($this->record->status, [
                    Contract::STATUS_IN_REVIEW,
                    Contract::STATUS_APPROVED,
                ], true)),

            Action::make('openEditor')
                ->label(__('app.action.open_editor'))
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->url(fn () => route('contracts.editor', ['contract' => $this->record]))
                ->visible(fn () => $this->record?->documentExists() && $this->record?->canBeEditedBy()),

            EditAction::make()
                ->visible(fn () => $this->record?->canBeEditedBy()),

            ...EditContract::approvalActions($this->record),
        ];
    }
}
