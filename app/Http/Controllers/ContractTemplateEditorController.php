<?php

namespace App\Http\Controllers;

use App\Filament\Resources\ContractTemplates\ContractTemplateResource;
use App\Models\ContractTemplate;
use App\Services\OnlyOffice\OnlyOfficeService;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ContractTemplateEditorController extends Controller
{
    public function show(ContractTemplate $template, OnlyOfficeService $service): View
    {
        if (! $template->templateExists()) {
            throw new NotFoundHttpException('Template document not uploaded yet.');
        }

        return view('contract-templates.editor', [
            'template' => $template,
            'apiScriptUrl' => $service->apiScriptUrl(),
            'config' => $service->templateEditorConfig($template, auth()->user()),
            'backUrl' => ContractTemplateResource::getUrl('edit', ['record' => $template]),
        ]);
    }
}
