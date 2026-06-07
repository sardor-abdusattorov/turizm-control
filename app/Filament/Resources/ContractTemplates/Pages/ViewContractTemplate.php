<?php

namespace App\Filament\Resources\ContractTemplates\Pages;

use App\Filament\Resources\ContractTemplates\ContractTemplateResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewContractTemplate extends ViewRecord
{
    protected static string $resource = ContractTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openEditor')
                ->label(__('app.action.open_template_in_editor'))
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->url(fn () => route('contract-templates.editor', ['template' => $this->record]))
                ->visible(fn () => $this->record?->templateExists()),

            EditAction::make(),
        ];
    }
}
