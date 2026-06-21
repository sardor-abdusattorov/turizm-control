<?php

namespace App\Console\Commands\Telegram;

use App\Services\Telegram\TelegramService;
use Illuminate\Console\Command;

class InstallWebhookCommand extends Command
{
    protected $signature = 'telegram:webhook:install
                            {--url= : Override the webhook URL (defaults to APP_URL + /telegram/webhook/<secret>)}';

    protected $description = 'Register this app as the Telegram bot webhook';

    public function handle(TelegramService $telegram): int
    {
        if (! $telegram->isConfigured()) {
            $this->error('TELEGRAM_BOT_TOKEN is not set in the environment.');

            return self::FAILURE;
        }

        $secret = (string) config('services.telegram.webhook_secret');

        if ($secret === '') {
            $this->error('TELEGRAM_WEBHOOK_SECRET is not set — refusing to install an unsecured webhook.');

            return self::FAILURE;
        }

        $url = (string) ($this->option('url') ?? route('telegram.webhook', ['secret' => $secret]));

        if (! str_starts_with($url, 'https://')) {
            $this->warn('Telegram only accepts HTTPS webhook URLs. Current URL: '.$url);
        }

        $this->info('Installing webhook → '.$url);

        $ok = $telegram->setWebhook($url, $secret);

        if (! $ok) {
            $this->error('Telegram rejected the webhook. Check the Laravel log for the API response.');

            return self::FAILURE;
        }

        $this->info('Webhook installed.');

        return self::SUCCESS;
    }
}
