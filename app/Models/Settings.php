<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Settings extends Model
{
    protected $table = 'settings';

    protected $fillable = ['key', 'value'];

    public function getValueAttribute($value)
    {
        if ($value === null) {
            return null;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $value;
    }

    public function setValueAttribute($value)
    {
        $this->attributes['value'] = json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    public const CACHE_KEY = 'settings.all';

    public static function get(string $key, mixed $default = null): mixed
    {
        $map = static::map();

        return array_key_exists($key, $map) ? $map[$key] : $default;
    }

    /** @return array<string, mixed> */
    public static function map(): array
    {
        return Cache::remember(
            static::CACHE_KEY,
            86400,
            fn (): array => static::query()->get()
                ->mapWithKeys(fn (self $setting): array => [$setting->key => $setting->value])
                ->all(),
        );
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        static::flush();
    }

    public static function flush(): void
    {
        Cache::forget(static::CACHE_KEY);
    }
}
