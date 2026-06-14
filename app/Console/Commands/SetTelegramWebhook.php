<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('telegram:set-webhook')]
#[Description('Register this app as the Telegram bot webhook')]
class SetTelegramWebhook extends Command
{
    public function handle(TelegramService $telegram): int
    {
        if (! $telegram->isConfigured()) {
            $this->error('TELEGRAM_BOT_TOKEN is not set.');

            return self::FAILURE;
        }

        $secret = config('services.telegram.webhook_secret');

        if (! $secret) {
            $this->error('TELEGRAM_WEBHOOK_SECRET is not set.');

            return self::FAILURE;
        }

        $url = route('telegram.webhook', ['secret' => $secret]);

        if ($telegram->setWebhook($url)) {
            $this->info("Webhook registered: {$url}");

            return self::SUCCESS;
        }

        $this->error('Telegram rejected the webhook registration. Check the logs.');

        return self::FAILURE;
    }
}
