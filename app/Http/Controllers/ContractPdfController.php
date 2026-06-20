<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Services\Contracts\ContractPdfService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ContractPdfController extends Controller
{
    public function download(Contract $contract, ContractPdfService $pdf): BinaryFileResponse
    {
        abort_unless($contract->canBeViewedBy(), 403);
        // PDF download is gated separately so view access alone doesn't grant
        // the ability to walk away with a rendered, signed contract.
        // super_admin is allowed by role directly so the action stays reachable
        // before the permission seeder has run.
        $user = auth()->user();
        abort_unless($user && ($user->hasRole('super_admin') || $user->can('export_contract')), 403);

        $path = $pdf->ensure($contract);

        if (! $path) {
            throw new NotFoundHttpException('Contract document not built yet.');
        }

        return response()->download($path, $contract->number.'.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function inline(Contract $contract, ContractPdfService $pdf): BinaryFileResponse
    {
        abort_unless($contract->canBeViewedBy(), 403);

        $path = $pdf->ensure($contract);

        if (! $path) {
            throw new NotFoundHttpException('Contract document not built yet.');
        }

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$contract->number.'.pdf"',
        ]);
    }
}
