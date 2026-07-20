<?php

namespace App\Models;

use App\Enums\OrderScope;
use App\Models\Concerns\HasActiveStatus;
use App\Models\Concerns\HasDocumentKey;
use App\Observers\OrderObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[ObservedBy(OrderObserver::class)]
class Order extends Model
{
    use HasActiveStatus;
    use HasDocumentKey;
    use HasFactory;

    protected $fillable = [
        'number',
        'scope',
        'title',
        'description',
        'file_path',
        'document_key',
        'issued_at',
        'status',
        'created_by',
    ];

    protected $casts = [
        'scope' => OrderScope::class,
        'status' => 'boolean',
        'issued_at' => 'date',
    ];

    public function registerYear(): ?int
    {
        return ($this->issued_at ?? $this->created_at)?->year;
    }

    /**
     * Active buyruqs a project can name as its basis, grouped into optgroups
     * by issue year (newest first), labelled by number (falling back to the
     * title for unnumbered drafts).
     *
     * @return array<string, array<int, string>>
     */
    public static function basisOptions(): array
    {
        return static::query()
            ->where('status', true)
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (Order $order): string => $order->issued_at?->format('Y') ?? __('app.label.not_set'))
            ->map(fn ($group) => $group->mapWithKeys(fn (Order $order): array => [
                $order->id => trim(($order->number ? $order->number.' · ' : '').$order->title),
            ])->toArray())
            ->toArray();
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
