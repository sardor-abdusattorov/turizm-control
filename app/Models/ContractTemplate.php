<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ContractTemplate extends Model
{
    use HasActiveStatus;
    use HasFactory;

    protected $fillable = [
        'contract_type_id',
        'name',
        'template_file',
        'sort',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'sort' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $template): void {
            if ($template->template_file && Storage::disk('local')->exists($template->template_file)) {
                Storage::disk('local')->delete($template->template_file);
            }
        });
    }

    public function templateAbsolutePath(): ?string
    {
        if (! $this->template_file) {
            return null;
        }

        return Storage::disk('local')->path($this->template_file);
    }

    public function templateExists(): bool
    {
        return $this->template_file
            && Storage::disk('local')->exists($this->template_file);
    }

    /**
     * Active templates usable for the given contract type: the ones tied to
     * that kind plus untyped "general" templates, in sort order.
     *
     * @param  Builder<ContractTemplate>  $query
     * @return Builder<ContractTemplate>
     */
    public function scopeForContractType(Builder $query, int $contractTypeId): Builder
    {
        return $query
            ->active()
            ->where(fn (Builder $inner) => $inner
                ->where('contract_type_id', $contractTypeId)
                ->orWhereNull('contract_type_id'))
            ->orderBy('sort');
    }

    public function contractType(): BelongsTo
    {
        return $this->belongsTo(ContractType::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
