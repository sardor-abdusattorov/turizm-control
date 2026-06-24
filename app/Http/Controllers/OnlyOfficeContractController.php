<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Services\OnlyOffice\OnlyOfficeCallbackHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            // Compared before the new bytes land on disk: an open-and-close
            // with no real edit downloads a document identical to the stored
            // one, so it must NOT reset the chain.
            hasContentChanged: fn (string $body): bool => ! $contract->documentExists()
                || md5($body) !== md5((string) Storage::disk('local')->get($contract->documentPath())),
            onFinalSave: function (array $editorIds, bool $changed) use ($contract): void {
                $contract->refreshDocumentKey();

                if ($changed) {
                    $contract->reinvalidateAfterDocumentEdit($editorIds);
                }
            },
            // A forcesave already wrote the edited document to disk, so when it
            // really changed the approval chain is stale right now — reset it
            // without waiting for the session-end save (and without rotating the
            // live key). Only when OnlyOffice names the editor, though: an
            // anonymous forcesave (a timed/system autosave) can't be attributed,
            // so we leave it for the authoritative status-2 save rather than
            // risk bouncing a contract out from under the approver mid-review.
            onForcesave: function (array $editorIds, bool $changed) use ($contract): void {
                if ($changed && $editorIds !== []) {
                    $contract->reinvalidateAfterDocumentEdit($editorIds);
                }
            },
            logProperties: ['contract_number' => $contract->number],
        );

        return response()->json($result);
    }
}
