<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_type_id',
        'title',
        'description',
        'file_path',
        'document_key',
        'deadline_at',
        'status',
        'created_by',
    ];

    protected $casts = [
        'status' => 'boolean',
        'deadline_at' => 'date',
    ];

    public const STATUS_INACTIVE = 0;

    public const STATUS_ACTIVE = 1;

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            if (! $order->document_key) {
                $order->document_key = static::generateDocumentKey();
            }
        });

        static::deleting(function (self $order): void {
            if ($order->file_path && Storage::disk('public')->exists($order->file_path)) {
                Storage::disk('public')->delete($order->file_path);
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

    public function fileAbsolutePath(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return Storage::disk('public')->path($this->file_path);
    }

    public function fileExists(): bool
    {
        return $this->file_path
            && Storage::disk('public')->exists($this->file_path);
    }

    public function isDocx(): bool
    {
        return $this->file_path
            && str_ends_with(strtolower($this->file_path), '.docx');
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
