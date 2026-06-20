<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pulls the daily UZS exchange rate from the Central Bank of Uzbekistan
 * (cbu.uz) for every active foreign currency and stores it on the
 * `currencies` table so contract totals stay in line with the official
 * rate. UZS itself is the base — its value is permanently 1.
 *
 * Scheduled in routes/console.php to run every day at 07:00. Wrapped in
 * withoutOverlapping() so a long-running fetch can't double up if the
 * scheduler runs while the previous invocation is still talking to the
 * CBU API.
 */
final class UpdateCurrencyRates extends Command
{
    protected $signature = 'currency:update';

    protected $description = 'Refresh foreign-currency UZS rates from cbu.uz.';

    private const ENDPOINT = 'https://cbu.uz/uz/arkhiv-kursov-valyut/json/';

    private const REQUEST_TIMEOUT = 10;

    public function handle(): int
    {
        $currencies = Currency::query()
            ->where('status', true)
            ->where('short_name', '!=', 'UZS')
            ->get();

        if ($currencies->isEmpty()) {
            $this->info('No foreign currencies to update.');

            return self::SUCCESS;
        }

        $updated = 0;
        $failed = 0;

        foreach ($currencies as $currency) {
            try {
                $rate = $this->fetchRate($currency->short_name);
            } catch (Throwable $e) {
                Log::warning('currency:update failed for '.$currency->short_name, [
                    'error' => $e->getMessage(),
                ]);
                $this->warn("  - {$currency->short_name}: ".$e->getMessage());
                $failed++;

                continue;
            }

            if ($rate === null) {
                Log::warning('currency:update returned no rate for '.$currency->short_name);
                $this->warn("  - {$currency->short_name}: no rate returned");
                $failed++;

                continue;
            }

            $previous = (float) $currency->value;
            $currency->value = $rate;
            $currency->save();

            $this->line("  - {$currency->short_name}: {$previous} -> {$rate}");
            $updated++;
        }

        $this->info("currency:update done — {$updated} updated, {$failed} failed.");

        Log::info('currency:update finished', [
            'updated' => $updated,
            'failed' => $failed,
        ]);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function fetchRate(string $shortName): ?float
    {
        $response = Http::timeout(self::REQUEST_TIMEOUT)
            ->acceptJson()
            ->get(self::ENDPOINT.$shortName.'/');

        if ($response->failed()) {
            throw new \RuntimeException('HTTP '.$response->status());
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data[0]['Rate'])) {
            return null;
        }

        $rate = (float) $data[0]['Rate'];

        return $rate > 0 ? $rate : null;
    }
}
