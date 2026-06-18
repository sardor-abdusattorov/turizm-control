<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContractTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_type_id',
        'name',
        'language',
        'template_file',
        'document_key',
        'sort',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'sort' => 'integer',
    ];

    public const STATUS_INACTIVE = 0;

    public const STATUS_ACTIVE = 1;

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

    public static function generateDocumentKey(): string
    {
        return Str::random(20);
    }

    public function refreshDocumentKey(): void
    {
        $this->update(['document_key' => static::generateDocumentKey()]);
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

    public static function getLanguages(): array
    {
        return [
            'ru' => __('app.label.ru'),
            'uz' => __('app.label.uz'),
            'en' => __('app.label.en'),
        ];
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => __('app.status.active'),
            self::STATUS_INACTIVE => __('app.status.inactive'),
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
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
