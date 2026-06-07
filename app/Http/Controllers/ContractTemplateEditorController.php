<?php

namespace App\Http\Controllers;

use App\Filament\Resources\ContractTemplates\ContractTemplateResource;
use App\Models\ContractTemplate;
use App\Services\OnlyOffice\OnlyOfficeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ContractTemplateEditorController extends Controller
{
    public function show(Request $request, ContractTemplate $template, OnlyOfficeService $service): Response
    {
        if (! $template->templateExists()) {
            throw new NotFoundHttpException('Template document not uploaded yet.');
        }

        return response()
            ->view('contract-templates.editor', [
                'template' => $template,
                'apiScriptUrl' => $service->apiScriptUrl(),
                'config' => $service->templateEditorConfig($template, auth()->user(), $request->query('mode')),
                'backUrl' => ContractTemplateResource::getUrl('view', ['record' => $template]),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
