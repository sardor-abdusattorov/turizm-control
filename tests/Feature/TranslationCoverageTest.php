<?php

use Illuminate\Support\Facades\File;

/**
 * A missing translation key is silent: Laravel renders the key itself and the
 * page still loads, so nothing fails until somebody reads "app.label.foo" on
 * screen. These guard the two ways that happens — a key referenced in code
 * that no locale defines, and a locale that has drifted from the others.
 */
function translationKeys(string $locale): array
{
    $flat = [];

    $walk = function (array $items, string $prefix) use (&$walk, &$flat): void {
        foreach ($items as $key => $value) {
            $path = $prefix ? "{$prefix}.{$key}" : $key;

            is_array($value) ? $walk($value, $path) : $flat[] = $path;
        }
    };

    $walk(require base_path("lang/{$locale}/app.php"), '');

    return $flat;
}

function sourceFiles(): string
{
    $blob = '';

    foreach (['app', 'resources', 'database', 'config', 'routes'] as $dir) {
        foreach (File::allFiles(base_path($dir)) as $file) {
            if (in_array($file->getExtension(), ['php', 'js'], true)) {
                $blob .= File::get($file->getPathname())."\n";
            }
        }
    }

    return $blob;
}

it('defines every translation key the code asks for', function () {
    $defined = array_flip(translationKeys('ru'));
    $source = sourceFiles();

    // Only fully literal keys can be checked — one assembled at runtime
    // ("app.approval.status.".$value) has no complete string to match.
    preg_match_all("/__\(\s*'(app\.[a-z0-9_.]+)'/", $source, $matches);

    $missing = collect($matches[1])
        ->unique()
        ->map(fn (string $key): string => substr($key, strlen('app.')))
        // A trailing separator means the match is the literal half of a key
        // finished at runtime, not a key in its own right.
        ->reject(fn (string $key): bool => str_ends_with($key, '.') || str_ends_with($key, '_'))
        ->reject(fn (string $key): bool => isset($defined[$key]))
        ->values()
        ->all();

    expect($missing)->toBe([], 'Ключи есть в коде, но не в lang/ru/app.php: '.implode(', ', $missing));
});

it('keeps every locale carrying the same keys', function () {
    $ru = translationKeys('ru');

    foreach (['en', 'uz'] as $locale) {
        $other = translationKeys($locale);

        expect(array_values(array_diff($ru, $other)))
            ->toBe([], "В lang/{$locale}/app.php не хватает ключей")
            ->and(array_values(array_diff($other, $ru)))
            ->toBe([], "В lang/{$locale}/app.php есть лишние ключи");
    }
});
