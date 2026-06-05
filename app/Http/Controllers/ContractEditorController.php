<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Services\OnlyOffice\OnlyOfficeService;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ContractEditorController extends Controller
{
    public function show(Contract $contract, OnlyOfficeService $service): View
    {
        if (! $contract->documentExists()) {
            throw new NotFoundHttpException('Contract document not built yet.');
        }

        return view('contracts.editor', [
            'contract' => $contract,
            'apiScriptUrl' => $service->apiScriptUrl(),
            'config' => $service->editorConfig($contract, auth()->user()),
            'backUrl' => ContractResource::getUrl('edit', ['record' => $contract]),
        ]);
    }
}
