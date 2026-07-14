<?php

namespace App\Models;

use App\Enums\ContractAttachmentType;
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
        $bytes = (int) $this->size;

        return $bytes >= 1024 * 1024
            ? number_format($bytes / 1024 / 1024, 2, '.', ' ').' MB'
            : number_format(max($bytes, 0) / 1024, 1, '.', ' ').' KB';
    }
}
