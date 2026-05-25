<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class Settings extends Model
{
    protected $table = 'settings';

    protected $fillable = ['key', 'value'];

    /**
     * Get the value attribute - decode JSON if it's a valid JSON string
     */
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

        // Otherwise return as-is
        return $value;
    }

    /**
     * Set the value attribute - encode arrays to JSON, store strings as-is
     */
    public function setValueAttribute($value)
    {
        $this->attributes['value'] = json_encode($value, JSON_UNESCAPED_UNICODE);
    }
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("settings.{$key}", 86400, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();

            return $setting ? $setting->value : $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    public static function getOgImage()
    {
        $path = settings('seo.og_image');

        if (! $path) {
            return null;
        }

        if (! str_starts_with($path, 'http')) {
            return asset(Storage::url($path));
        }

        return $path;
    }
}
