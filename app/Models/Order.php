<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use App\Models\Concerns\HasDocumentKey;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Order extends Model
{
    use HasActiveStatus;
    use HasDocumentKey;
    use HasFactory;

    protected $fillable = [
        'number',
        'order_type_id',
        'title',
        'description',
        'file_path',
        'document_key',
        'issued_at',
        'deadline_at',
        'status',
        'created_by',
    ];

    protected $casts = [
        'status' => 'boolean',
        'issued_at' => 'date',
        'deadline_at' => 'date',
    ];

    /** Prefix used by generateNumber() — matches the existing КОНТ-… contract style. */
    public const NUMBER_PREFIX = 'ПРК';

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            if (! $order->document_key) {
                $order->document_key = static::generateDocumentKey();
            }

            if (! $order->number) {
                $order->number = static::generateNumber($order->issued_at);
            }
        });

        static::deleting(function (self $order): void {
            if ($order->file_path && Storage::disk('local')->exists($order->file_path)) {
                Storage::disk('local')->delete($order->file_path);
            }
        });
    }

    /**
     * Sequential register number — "ПРК-2026-001". Uses the issued_at
     * year when present, otherwise the current year. The numbering loops
     * back to 001 every year.
     */
    public static function generateNumber(\DateTimeInterface|CarbonInterface|null $issuedAt = null): string
    {
        $year = $issuedAt ? (int) $issuedAt->format('Y') : now()->year;
        $prefix = self::NUMBER_PREFIX;

        $lastSeq = static::query()
            ->where('number', 'like', "{$prefix}-{$year}-%")
            ->orderByDesc('id')
            ->value('number');

        $next = $lastSeq
            ? ((int) substr($lastSeq, strrpos($lastSeq, '-') + 1)) + 1
            : 1;

        return sprintf('%s-%d-%03d', $prefix, $year, $next);
    }

    /** Year the order belongs to — driven by issued_at, falls back to created_at. */
    public function registerYear(): ?int
    {
        return ($this->issued_at ?? $this->created_at)?->year;
    }

    public function fileAbsolutePath(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return Storage::disk('local')->path($this->file_path);
    }

    public function fileExists(): bool
    {
        return $this->file_path
            && Storage::disk('local')->exists($this->file_path);
    }

    public function isOpenableInOnlyOffice(): bool
    {
        return in_array($this->extension(), ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'], true);
    }

    public function extension(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return strtolower(pathinfo($this->file_path, PATHINFO_EXTENSION));
    }

    public function documentColor(): string
    {
        return match ($this->extension()) {
            'doc', 'docx' => 'info',
            'xls', 'xlsx', 'csv' => 'success',
            'ppt', 'pptx' => 'warning',
            'pdf' => 'danger',
            default => 'gray',
        };
    }

    public function documentIcon(): string
    {
        return match ($this->extension()) {
            'xls', 'xlsx', 'csv' => 'heroicon-o-table-cells',
            'ppt', 'pptx' => 'heroicon-o-presentation-chart-bar',
            'pdf' => 'heroicon-o-document',
            default => 'heroicon-o-document-text',
        };
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
