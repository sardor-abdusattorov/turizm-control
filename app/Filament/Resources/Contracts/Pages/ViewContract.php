<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Widgets\ContractPdfPreview;
use App\Models\Contract;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    protected function getFooterWidgets(): array
    {
        return [
            ContractPdfPreview::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label(__('app.action.download_pdf'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn () => route('contracts.pdf.download', ['contract' => $this->record]))
                ->visible(fn () => $this->record?->status === Contract::STATUS_APPROVED),

            Action::make('openEditor')
                ->label(__('app.action.open_editor'))
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn () => route('contracts.editor', [
                    'contract' => $this->record,
                    'mode' => 'view',
                ]))
                ->visible(fn () => $this->record?->documentExists()),

            EditAction::make()
                ->visible(fn () => $this->record?->canBeEditedBy()),

            ...EditContract::approvalActions($this->record),
        ];
    }
}
