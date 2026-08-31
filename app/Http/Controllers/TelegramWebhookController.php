<?php

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request, string $secret, TelegramBot $bot): JsonResponse
    {
        $expected = (string) config('services.telegram.webhook_secret');

        abort_unless($expected !== '' && hash_equals($expected, $secret), 403);

        abort_unless(
            hash_equals($expected, (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '')),
            403,
        );

        $bot->handleUpdate($request->all());

        return response()->json(['ok' => true]);
    }
}
