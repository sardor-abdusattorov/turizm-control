<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Services\OnlyOffice\OnlyOfficeService;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EditContractDocument extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ContractResource::class;

    protected string $view = 'filament.resources.contracts.pages.edit-document';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        if (! $this->record->documentExists()) {
            throw new NotFoundHttpException('Contract document not built yet.');
        }
    }

    public function getTitle(): string|Htmlable
    {
        return __('app.label.contract_single').' '.$this->record->number;
    }

    public function getBreadcrumb(): string
    {
        return __('app.action.open_editor');
    }

    protected function getViewData(): array
    {
        $service = app(OnlyOfficeService::class);

        return [
            'apiScriptUrl' => $service->apiScriptUrl(),
            'config' => $service->editorConfig($this->record, Auth::user()),
        ];
    }
}
