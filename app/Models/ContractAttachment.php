<?php

namespace App\Models;

use App\Enums\ContractAttachmentType;
use App\Support\Bytes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ContractAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'type',
        'file_path',
        'original_name',
        'size',
        'uploaded_by',
        'sort',
    ];

    protected $casts = [
        'type' => ContractAttachmentType::class,
        'size' => 'integer',
        'sort' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $attachment): void {
            if ($attachment->file_path && Storage::disk('local')->exists($attachment->file_path)) {
                Storage::disk('local')->delete($attachment->file_path);
            }
        });
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Signed expiring link, mirroring the gallery/payment-screenshot pattern —
     * the private disk serves files only through temporary URLs.
     */
    public function url(): ?string
    {
        if (! $this->file_path || ! Storage::disk('local')->exists($this->file_path)) {
            return null;
        }

        return Storage::disk('local')->temporaryUrl($this->file_path, now()->addMinutes(30));
    }

    public function sizeLabel(): string
    {
        return Bytes::human($this->size);
    }

    public function extension(): ?string
    {
        $extension = strtolower(pathinfo((string) $this->file_path, PATHINFO_EXTENSION));

        return $extension !== '' ? $extension : null;
    }

    /**
     * Word files open through the OnlyOffice viewer; browsers cannot
     * render them natively.
     */
    public function isWordDocument(): bool
    {
        return in_array($this->extension(), ['doc', 'docx'], true);
    }

    /**
     * Types the browser previews itself (with print and save built in).
     */
    public function isBrowserViewable(): bool
    {
        return in_array($this->extension(), ['pdf', 'jpg', 'jpeg', 'png'], true);
    }

    public function fileExists(): bool
    {
        return $this->file_path && Storage::disk('local')->exists($this->file_path);
    }

    public function absolutePath(): string
    {
        return Storage::disk('local')->path((string) $this->file_path);
    }

    /**
     * OnlyOffice cache key — stable per stored file version.
     */
    public function documentKey(): string
    {
        return 'att-'.$this->id.'-'.substr(md5($this->file_path.'|'.($this->updated_at?->timestamp ?? 0)), 0, 12);
    }

    /**
     * The secret the OnlyOffice server presents when fetching the file —
     * derived, so no extra column is needed.
     */
    public function sharedKey(): string
    {
        return hash_hmac('sha256', 'contract-attachment|'.$this->id.'|'.$this->file_path, (string) config('app.key'));
    }
}
