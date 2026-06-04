<?php

namespace App\Filament\Pages;

use App\Services\OnlyOffice\OnlyOfficeService;
use Filament\Pages\Page;
use Illuminate\Support\Str;

class OnlyOfficeTest extends Page
{
    protected string $view = 'filament.pages.only-office-test';

    protected static ?string $slug = 'onlyoffice-test';

    public static function getNavigationLabel(): string
    {
        return 'OnlyOffice Test';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-beaker';
    }

    public function getTitle(): string
    {
        return 'OnlyOffice Test';
    }

    protected function getViewData(): array
    {
        $service = app(OnlyOfficeService::class);

        $host = rtrim((string) config('onlyoffice.callback_host'), '/');

        $config = $service->configForFile(
            key: Str::random(20),
            title: 'test.docx',
            documentUrl: $host.'/onlyoffice-test/document',
            callbackUrl: $host.'/onlyoffice-test/callback',
            edit: true,
        );

        return [
            'apiScriptUrl' => $service->apiScriptUrl(),
            'config' => $config,
        ];
    }
}
