<?php

namespace App\Models;

use App\Enums\PressTourAttachmentType;
use App\Support\Bytes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A paper a finished tour left behind — the report, the media coverage it
 * generated, photos, the programme, the participant list, an act. Mirrors
 * ContractAttachment: private disk, signed expiring links, file removed with
 * the record.
 */
class PressTourAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'press_tour_id',
        'type',
        'file_path',
        'original_name',
        'size',
        'uploaded_by',
        'sort',
    ];

    protected $casts = [
        'type' => PressTourAttachmentType::class,
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

    public function pressTour(): BelongsTo
    {
        return $this->belongsTo(PressTour::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Signed expiring link — the private disk serves files only this way.
     */
    public function url(): ?string
    {
        if (! $this->fileExists()) {
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

    public function fileExists(): bool
    {
        return $this->file_path && Storage::disk('local')->exists($this->file_path);
    }
}
