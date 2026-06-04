<?php

namespace App\Http\Controllers;

use App\Services\OnlyOffice\OnlyOfficeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OnlyOfficeTestController extends Controller
{
    private const PATH = 'onlyoffice-test/test.docx';

    public function document(): BinaryFileResponse
    {
        abort_unless(Storage::disk('local')->exists(self::PATH), 404);

        return response()->download(Storage::disk('local')->path(self::PATH), 'test.docx');
    }

    public function callback(Request $request, OnlyOfficeService $service): JsonResponse
    {
        $status = (int) $request->input('status', 0);
        $url = $request->input('url');

        if (in_array($status, [2, 6], true) && is_string($url)) {
            try {
                $body = Http::timeout(30)->get($service->internalDownloadUrl($url))->body();
                Storage::disk('local')->put(self::PATH, $body);
                Log::info('OnlyOffice saved', ['bytes' => strlen($body)]);
            } catch (\Throwable $e) {
                Log::error('OnlyOffice save failed', ['url' => $url, 'error' => $e->getMessage()]);

                return response()->json(['error' => 1]);
            }
        }

        return response()->json(['error' => 0]);
    }
}
