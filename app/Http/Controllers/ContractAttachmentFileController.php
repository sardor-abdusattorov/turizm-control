<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractAttachment;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

class ContractAttachmentFileController extends Controller
{
    /**
     * Serve a dossier file inline — the browser's own viewer covers PDFs and
     * images with print and save built in. Word files have no in-browser
     * viewer since the OnlyOffice integration was dropped, so the attachments
     * table offers them as a download instead.
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
