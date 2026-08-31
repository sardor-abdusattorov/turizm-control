<?php

use App\Models\Settings;

if (! function_exists('settings')) {
    function settings(string $key, mixed $default = null): mixed
    {
        return Settings::get($key, $default);
    }
}

if (! function_exists('format_percent')) {
    function format_percent(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}

if (! function_exists('clear_settings_cache')) {
    function clear_settings_cache(): void
    {
        Settings::flush();
    }
}
