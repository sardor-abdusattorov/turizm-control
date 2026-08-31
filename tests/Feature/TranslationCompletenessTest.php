<?php

use Illuminate\Support\Facades\Lang;

/** @return array<string, array<int, string>> key => files using it */
function collectUsedAppTranslationKeys(): array
{
    $keys = [];

    foreach ([app_path(), resource_path('views'), database_path()] as $dir) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            preg_match_all(
                "/(?:__|trans)\\(\\s*'(app\\.[A-Za-z0-9_.-]+)'\\s*[,)]/",
                (string) file_get_contents($file->getPathname()),
                $matches,
            );

            foreach ($matches[1] as $key) {
                if (! str_ends_with($key, '.')) {
                    $keys[$key][] = str_replace(base_path().'/', '', $file->getPathname());
                }
            }
        }
    }

    ksort($keys);

    return $keys;
}

it('has every used app.* translation key in all three locales', function () {
    $keys = collectUsedAppTranslationKeys();

    expect($keys)->not->toBeEmpty();

    $missing = [];

    foreach ($keys as $key => $files) {
        foreach (['ru', 'uz', 'en'] as $locale) {
            if (! Lang::has($key, $locale)) {
                $missing[] = "[{$locale}] {$key} (used in ".implode(', ', array_unique($files)).')';
            }
        }
    }

    expect($missing)->toBe([]);
});
