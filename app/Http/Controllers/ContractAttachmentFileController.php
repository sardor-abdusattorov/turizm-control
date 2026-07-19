<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Models\ContractAttachment;
use App\Services\OnlyOffice\OnlyOfficeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

class ContractAttachmentFileController extends Controller
{
    /**
     * Serve a dossier file inline — the browser's own viewer covers PDFs and
     * images with print and save built in.
     */
    public function inline(Contract $contract, ContractAttachment $attachment): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can('view', $contract) ?? false, 403);
        abort_unless((int) $attachment->contract_id === (int) $contract->id, 404);
        abort_unless($attachment->fileExists(), 404);

        $name = (string) $attachment->original_name;

        return response()->file($attachment->absolutePath(), [
            'Content-Type' => $this->mimeFor($attachment->extension()),
            // Headers are latin-1 — a Cyrillic filename must ride the RFC 5987
            // filename* form, with an ASCII fallback for old agents.
            'Content-Disposition' => HeaderUtils::makeDisposition(
                'inline',
                $name,
                Str::ascii($name) !== '' ? Str::ascii($name) : 'file.'.($attachment->extension() ?? 'bin'),
            ),
        ]);
    }

    /**
     * OnlyOffice read-only viewer for Word dossier files.
     */
    public function viewer(Contract $contract, ContractAttachment $attachment, OnlyOfficeService $service): Response
    {
        abort_unless(auth()->user()?->can('view', $contract) ?? false, 403);
        abort_unless((int) $attachment->contract_id === (int) $contract->id, 404);
        abort_unless($attachment->fileExists() && $attachment->isWordDocument(), 404);

        return response()
            ->view('contract-attachments.viewer', [
                'attachment' => $attachment,
                'contract' => $contract,
                'apiScriptUrl' => $service->apiScriptUrl(),
                'config' => $service->attachmentViewerConfig($attachment, auth()->user()),
                'backUrl' => ContractResource::getUrl('view', ['record' => $contract]),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * The endpoint the OnlyOffice server fetches the file from — no session,
     * authenticated by the derived shared key instead.
     */
    public function document(Request $request, ContractAttachment $attachment): BinaryFileResponse
    {
        abort_unless(hash_equals($attachment->sharedKey(), (string) $request->query('shared_key')), 403);
        abort_unless($attachment->fileExists(), 404);

        return response()->download($attachment->absolutePath(), (string) $attachment->original_name);
    }

    private function mimeFor(?string $extension): string
    {
        return match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };
    }
}
