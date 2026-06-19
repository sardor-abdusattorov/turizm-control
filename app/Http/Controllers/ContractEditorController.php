<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Services\OnlyOffice\OnlyOfficeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ContractEditorController extends Controller
{
    public function show(Request $request, Contract $contract, OnlyOfficeService $service): Response
    {
        abort_unless($contract->canBeViewedBy(), 403);

        if (! $contract->documentExists()) {
            throw new NotFoundHttpException('Contract document not built yet.');
        }

        return response()
            ->view('contracts.editor', [
                'contract' => $contract,
                'apiScriptUrl' => $service->apiScriptUrl(),
                'config' => $service->editorConfig($contract, auth()->user(), $request->query('mode')),
                'backUrl' => ContractResource::getUrl('view', ['record' => $contract]),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
