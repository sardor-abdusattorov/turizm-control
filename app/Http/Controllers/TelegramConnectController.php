<?php

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramBot;
use Illuminate\Http\RedirectResponse;

class TelegramConnectController extends Controller
{
    public function connect(TelegramBot $bot): RedirectResponse
    {
        $url = $bot->connectUrl(auth()->user());

        abort_if($url === null, 404, 'Telegram bot is not configured.');

        return redirect()->away($url);
    }
}
