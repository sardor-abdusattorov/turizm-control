<?php

namespace App\Http\Controllers;

use App\Models\ContractTemplate;
use App\Services\OnlyOffice\OnlyOfficeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OnlyOfficeTemplateController extends Controller
{
    public function document(Request $request, ContractTemplate $template): BinaryFileResponse
    {
        $this->ensureSharedKey($request, $template);

        abort_unless($template->templateExists(), 404);

        return response()->download(
            $template->templateAbsolutePath(),
            $template->name.'.docx',
        );
    }

    public function callback(Request $request, ContractTemplate $template, OnlyOfficeService $service): JsonResponse
    {
        $this->ensureSharedKey($request, $template);

        $status = (int) $request->input('status', 0);
        $url = $request->input('url');

        if (in_array($status, [2, 6], true) && is_string($url)) {
            try {
                $body = Http::timeout(60)
                    ->get($service->internalDownloadUrl($url))
                    ->body();

                Storage::disk('public')->put($template->template_file, $body);

                if ($status === 2) {
                    $template->refreshDocumentKey();
                }

                Log::info('Contract template saved', [
                    'template_id' => $template->id,
                    'status' => $status,
                    'bytes' => strlen($body),
                ]);
            } catch (\Throwable $e) {
                Log::error('Contract template save failed', [
                    'template_id' => $template->id,
                    'error' => $e->getMessage(),
                ]);

                return response()->json(['error' => 1]);
            }
        }

        return response()->json(['error' => 0]);
    }

    private function ensureSharedKey(Request $request, ContractTemplate $template): void
    {
        $provided = (string) $request->query('shared_key', '');

        abort_unless(
            $provided !== '' && hash_equals((string) $template->document_key, $provided),
            403,
        );
    }
}
