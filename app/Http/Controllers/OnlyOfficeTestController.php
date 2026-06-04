<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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

    public function callback(Request $request): JsonResponse
    {
        $status = (int) $request->input('status', 0);
        $url = $request->input('url');

        if (in_array($status, [2, 6], true) && is_string($url)) {
            Storage::disk('local')->put(self::PATH, Http::get($url)->body());
        }

        return response()->json(['error' => 0]);
    }
}
