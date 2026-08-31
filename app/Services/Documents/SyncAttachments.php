<?php

namespace App\Services\Documents;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SyncAttachments
{
    /**
     * @param  HasMany<Model, Model>  $relation
     * @param  array<int|string, mixed>  $paths  FileUpload state: stored paths
     * @param  array<int|string, string>  $names  storeFileNamesIn payload, keyed by path
     * @param  array<string, mixed>  $attributesForNew  extra columns for freshly added rows
     */
    public function sync(HasMany $relation, array $paths, array $names = [], array $attributesForNew = []): void
    {
        $submitted = $this->submittedPaths($paths);
        $existing = $relation->get();
        $known = $existing->pluck('file_path')->all();

        $sort = (int) $relation->max('sort');

        foreach ($submitted as $path) {
            if (in_array($path, $known, true)) {
                continue;
            }

            $relation->create([
                ...$attributesForNew,
                'file_path' => $path,
                'original_name' => $this->nameFor($path, $names, $paths),
                'size' => Storage::disk('local')->exists($path) ? Storage::disk('local')->size($path) : 0,
                'uploaded_by' => Auth::id(),
                'sort' => ++$sort,
            ]);
        }

        $existing
            ->reject(fn (Model $attachment): bool => in_array($attachment->file_path, $submitted, true))
            ->filter(fn (Model $attachment): bool => Storage::disk('local')->exists((string) $attachment->file_path))
            ->each(fn (Model $attachment) => $attachment->delete());
    }

    /**
     * @param  array<int|string, mixed>  $paths
     * @return list<string>
     */
    private function submittedPaths(array $paths): array
    {
        return array_values(array_filter(
            array_map(strval(...), array_values($paths)),
            fn (string $path): bool => $path !== '',
        ));
    }

    /**
     * @param  array<int|string, string>  $names
     * @param  array<int|string, mixed>  $paths
     */
    private function nameFor(string $path, array $names, array $paths): string
    {
        $key = array_search($path, array_map(strval(...), $paths), strict: true);

        return $names[$path]
            ?? ($key !== false ? ($names[$key] ?? null) : null)
            ?? basename($path);
    }
}
