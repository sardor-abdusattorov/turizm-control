<?php

namespace App\Services\Documents;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Syncs a HasMany of attachment rows to the state of a Filament FileUpload
 * panel: the submitted list of stored paths IS the dossier, in order.
 *
 * Shared by the contract form, the contract view page's dossier panel and the
 * press-tour documents panel, so a file behaves identically wherever it is
 * added — one place that knows how Filament's payload maps onto our rows.
 */
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

        // Additions run FIRST. Deleting a row unlinks its file through the
        // model's deleting hook, which no transaction can roll back — so a
        // throw while filing must not leave the dossier already gutted.
        //
        // Filed files keep the position they were filed in: the panel's own
        // array order is not a reliable statement of intent (a fresh upload
        // lands ahead of the existing chips in submitted state), so new files
        // append after the current tail rather than renumbering the dossier.
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

        // A path that vanished from the panel was removed by the user — the
        // row goes, and the model's deleting hook takes the file with it.
        //
        // Guard: FileUpload silently drops chips whose file is missing on
        // disk, so a record with a lost file must not be mistaken for a
        // deliberate removal.
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
     * Filament keys the stored names by the stored PATH, not by the upload
     * uuid — looking up by the state key alone always missed.
     *
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
