<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait SearchesByTaxId
{
    /** @return array<int, string> */
    public static function searchOptions(?string $search = null, int $limit = 50): array
    {
        return static::query()
            ->active()
            ->when(filled($search), fn (Builder $query) => $query->where(
                fn (Builder $inner) => collect(static::taxIdSearchColumns())
                    ->each(fn (string $column) => $inner->orWhere($column, 'like', '%'.$search.'%')),
            ))
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn (self $record): array => [$record->getKey() => $record->optionLabel()])
            ->all();
    }

    public function optionLabel(): string
    {
        $name = e($this->searchableName());

        foreach (static::taxIdColumns() as $column => $labelKey) {
            if (filled($this->{$column})) {
                return sprintf(
                    '<span style="display:inline-flex;align-items:baseline;gap:.45rem;flex-wrap:wrap;">'
                    .'<span>%s</span>'
                    .'<span style="font-size:.75rem;opacity:.55;font-variant-numeric:tabular-nums;white-space:nowrap;">%s %s</span>'
                    .'</span>',
                    $name,
                    e(__($labelKey)),
                    e($this->{$column}),
                );
            }
        }

        return $name;
    }

    /** @return array<string, string> */
    protected static function taxIdColumns(): array
    {
        return ['inn' => 'app.label.inn'];
    }

    /** @return list<string> */
    protected static function taxIdSearchColumns(): array
    {
        return ['name', ...array_keys(static::taxIdColumns())];
    }

    protected function searchableName(): string
    {
        return (string) $this->name;
    }
}
