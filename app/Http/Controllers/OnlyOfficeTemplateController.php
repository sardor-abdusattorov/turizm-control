<?php

namespace App\Http\Controllers;

use App\Models\ContractTemplate;
use App\Services\OnlyOffice\OnlyOfficeCallbackHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OnlyOfficeTemplateController extends Controller
{
    public function __construct(public OnlyOfficeCallbackHandler $callback) {}

    public function document(Request $request, ContractTemplate $template): BinaryFileResponse
    {
        $this->callback->ensureSharedKey($request, $template->document_key);

        abort_unless($template->templateExists(), 404);

        return response()->download(
            $template->templateAbsolutePath(),
            $template->name.'.docx',
        );
    }

    public function callback(Request $request, ContractTemplate $template): JsonResponse
    {
        $this->callback->ensureSharedKey($request, $template->document_key);

        $result = $this->callback->handle(
            request: $request,
            subject: $template,
            savedEvent: 'Template Saved',
            forcesaveEvent: 'Template Forcesave',
            logDescription: 'Template "'.$template->name.'" saved via OnlyOffice',
            persist: $this->callback->persistToDisk('local', $template->template_file),
            onFinalSave: fn () => $template->refreshDocumentKey(),
            logProperties: ['template_name' => $template->name],
        );

        return response()->json($result);
    }
}
