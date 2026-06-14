<?php

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request, string $secret, TelegramBot $bot): JsonResponse
    {
        $expected = config('services.telegram.webhook_secret');

        abort_unless($expected && hash_equals((string) $expected, $secret), 403);

        $bot->handleUpdate($request->all());

        return response()->json(['ok' => true]);
    }
}
