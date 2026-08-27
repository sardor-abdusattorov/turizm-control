<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

class ContractDocumentController extends Controller
{
    /**
     * Hand the contract's working document over as a download. With the
     * OnlyOffice editor and its docx→PDF conversion gone, downloading the
     * file is how a contract leaves the system — the browser cannot render
     * a .docx on its own.
     */
    public function download(Contract $contract): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can('view', $contract) ?? false, 403);
        abort_unless($contract->documentExists(), 404);

        $name = trim((string) $contract->number) !== ''
            ? $contract->number.'.docx'
            : 'contract.docx';

        return response()->download(
            $contract->documentAbsolutePath(),
            $name,
            // Headers are latin-1, so a Cyrillic contract number has to ride
            // the RFC 5987 filename* form with an ASCII fallback.
            ['Content-Disposition' => HeaderUtils::makeDisposition(
                'attachment',
                $name,
                Str::ascii($name) !== '' ? Str::ascii($name) : 'contract.docx',
            )],
        );
    }
}
