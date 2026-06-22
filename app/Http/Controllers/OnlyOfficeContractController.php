<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\User;
use App\Services\OnlyOffice\OnlyOfficeCallbackHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OnlyOfficeContractController extends Controller
{
    public function __construct(public OnlyOfficeCallbackHandler $callback) {}

    public function document(Request $request, Contract $contract): BinaryFileResponse
    {
        $this->callback->ensureSharedKey($request, $contract->document_key);

        abort_unless($contract->documentExists(), 404);

        return response()->download(
            $contract->documentAbsolutePath(),
            $contract->number.'.docx',
        );
    }

    public function callback(Request $request, Contract $contract): JsonResponse
    {
        $this->callback->ensureSharedKey($request, $contract->document_key);

        $result = $this->callback->handle(
            request: $request,
            subject: $contract,
            savedEvent: 'Contract Document Saved',
            forcesaveEvent: 'Contract Document Forcesave',
            logDescription: 'Contract '.$contract->number.' saved via OnlyOffice',
            persist: $this->callback->persistToDisk('local', $contract->documentPath()),
            onFinalSave: function (?User $editor) use ($contract): void {
                $contract->refreshDocumentKey();
                $contract->reinvalidateAfterDocumentEdit($editor);
            },
            logProperties: ['contract_number' => $contract->number],
        );

        return response()->json($result);
    }
}
