<?php

namespace App\Filament\Pages;

use App\Models\Contract;
use App\Services\OnlyOffice\OnlyOfficeService;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ContractDocumentEditor extends Page
{
    protected string $view = 'filament.pages.contract-document-editor';

    protected static ?string $slug = 'contracts/{record}/editor';

    public ?Contract $record = null;

    public function mount(int|string $record): void
    {
        $this->record = Contract::query()->findOrFail($record);

        if (! $this->record->documentExists()) {
            throw new NotFoundHttpException('Contract document not built yet.');
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record
            ? __('app.label.contract_single').' '.$this->record->number
            : __('app.label.contract_single');
    }

    public function getBreadcrumbs(): array
    {
        return [
            url('/admin/contracts') => __('app.label.contract_plural'),
            $this->record?->number ?? '',
        ];
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
