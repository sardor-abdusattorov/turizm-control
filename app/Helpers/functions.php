<?php

use App\Models\Settings;
use Illuminate\Support\Facades\Cache;

if (! function_exists('settings')) {
    /**
     * @param  string  $key  Setting key (e.g., 'seo.title', 'metrics.yandex')
     * @param  mixed  $default  Default value if not found
     */
    function settings(string $key, mixed $default = null): mixed
    {
        $cacheKey = "settings.{$key}";

        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            $setting = Settings::where('key', $key)->first();

            return $setting ? $setting->value : $default;
        });
    }
}

if (! function_exists('format_percent')) {
    function format_percent(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}

if (! function_exists('clear_settings_cache')) {
    /** @param  string|null  $key  Setting key (if null - clear all) */
    function clear_settings_cache(?string $key = null): void
    {
        if ($key) {
            Cache::forget("settings.{$key}");
        } else {
            Settings::all()->each(function ($setting) {
                Cache::forget("settings.{$setting->key}");
            });
        }
    }
}
