<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\User;
use App\Services\OnlyOffice\OnlyOfficeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use MrAdder\FilamentLogger\Facades\FilamentLogger;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OnlyOfficeContractController extends Controller
{
    public function document(Request $request, Contract $contract): BinaryFileResponse
    {
        $this->ensureSharedKey($request, $contract);

        abort_unless($contract->documentExists(), 404);

        return response()->download(
            $contract->documentAbsolutePath(),
            $contract->number.'.docx',
        );
    }

    public function callback(Request $request, Contract $contract, OnlyOfficeService $service): JsonResponse
    {
        $this->ensureSharedKey($request, $contract);

        $status = (int) $request->input('status', 0);
        $url = $request->input('url');

        if (in_array($status, [2, 6], true) && is_string($url)) {
            try {
                $body = Http::timeout(60)
                    ->get($service->internalDownloadUrl($url))
                    ->body();

                Storage::disk('local')->put($contract->documentPath(), $body);

                if ($status === 2) {
                    $contract->refreshDocumentKey();
                }

                Log::info('Contract document saved', [
                    'contract_id' => $contract->id,
                    'status' => $status,
                    'bytes' => strlen($body),
                ]);

                $userIds = $request->input('users', []);

                FilamentLogger::log(
                    event: $status === 2 ? 'Contract Document Saved' : 'Contract Document Forcesave',
                    description: 'Contract '.$contract->number.' saved via OnlyOffice',
                    options: [
                        'logName' => 'Document',
                        'subject' => $contract,
                        'causer' => is_array($userIds) ? User::find($userIds[0] ?? null) : null,
                        'properties' => [
                            'status' => $status,
                            'bytes' => strlen($body),
                            'contract_number' => $contract->number,
                        ],
                    ],
                );
            } catch (\Throwable $e) {
                Log::error('Contract document save failed', [
                    'contract_id' => $contract->id,
                    'error' => $e->getMessage(),
                ]);

                return response()->json(['error' => 1]);
            }
        }

        return response()->json(['error' => 0]);
    }

    private function ensureSharedKey(Request $request, Contract $contract): void
    {
        $provided = (string) $request->query('shared_key', '');

        abort_unless(
            $provided !== '' && hash_equals((string) $contract->document_key, $provided),
            403,
        );
    }
}
