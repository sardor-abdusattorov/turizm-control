<?php

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    /**
     * The secret is verified twice: in the URL path (so a guessed route 403s)
     * and in the X-Telegram-Bot-Api-Secret-Token header, which Telegram sends
     * on every update because telegram:webhook:install registers it — the
     * path can leak into proxy access logs, the header cannot.
     */
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
