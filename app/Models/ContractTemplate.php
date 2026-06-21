<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use App\Models\Concerns\HasDocumentKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ContractTemplate extends Model
{
    use HasActiveStatus;
    use HasDocumentKey;
    use HasFactory;

    protected $fillable = [
        'order_type_id',
        'name',
        'template_file',
        'document_key',
        'sort',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'sort' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $template): void {
            if (! $template->document_key) {
                $template->document_key = static::generateDocumentKey();
            }
        });

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

    public function orderType(): BelongsTo
    {
        return $this->belongsTo(OrderType::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
