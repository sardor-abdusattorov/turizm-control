<?php

namespace App\Livewire;

use App\Filament\Support\MediaGalleryUpload;
use App\Filament\Support\PaymentFilesUpload;
use App\Models\Payment;
use App\Models\Project;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Read-only media viewer built on Filament's own FileUpload — the same grid the
 * edit forms use, but locked, so a view page shows photos, videos and payment
 * proofs with open/download and no editing. Adding or removing files happens on
 * the record's Edit page, which owns the writable FileUpload.
 *
 * Two variants share the plumbing: the project gallery (`project-gallery`) and
 * the payment proofs (`payment-screenshots`).
 */
class MediaLibrary extends Component implements HasForms
{
    use InteractsWithForms;
    use RestrictsFileUploadsToSchemaComponents;

    /**
     * Locked: mount() authorises the record once, and Livewire would otherwise
     * let the client repoint these on any later request — after which no gate
     * runs again.
     */
    #[Locked]
    public string $variant;

    #[Locked]
    public int $recordId;

    /**
     * When the record has no displayable files, the payment view wants the
     * whole card gone (a bare "нет файлов" panel reads as broken); the gallery
     * tab keeps the empty-state message instead.
     */
    public bool $hideWhenEmpty = false;

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    private ?Model $mediaRecord = null;

    public function mount(): void
    {
        // $recordId arrives from the client, so the viewer carries its own
        // gate rather than trusting the page that embedded it.
        abort_unless(Gate::allows('view', $this->record()), 403);

        // Only feed the viewer files that actually exist on disk. Snapshot
        // rebuilds restore file *paths* but not the uploads themselves, so a
        // stale path would otherwise render as an empty, broken FilePond tile.
        $this->form->fill([
            $this->field() => $this->existingFiles(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([$this->uploadField()])
            ->statePath('data');
    }

    public function headerIcon(): string
    {
        return match ($this->variant) {
            'project-gallery' => 'heroicon-o-photo',
            'payment-screenshots' => 'heroicon-o-paper-clip',
            default => 'heroicon-o-photo',
        };
    }

    public function headerTitle(): string
    {
        return match ($this->variant) {
            'project-gallery' => __('app.label.gallery'),
            'payment-screenshots' => __('app.label.screenshot'),
            default => __('app.label.gallery'),
        };
    }

    public function hasFiles(): bool
    {
        return filled($this->existingFiles());
    }

    /**
     * The record's stored paths, narrowed to files still present on the disk.
     *
     * @return list<string>
     */
    private function existingFiles(): array
    {
        $paths = (array) ($this->record()->{$this->field()} ?? []);

        // Both variants store on the private 'local' disk.
        return array_values(array_filter(
            $paths,
            fn ($path): bool => filled($path) && Storage::disk('local')->exists($path),
        ));
    }

    public function render(): View
    {
        return view('livewire.media-library');
    }

    private function uploadField(): FileUpload
    {
        $field = match ($this->variant) {
            'project-gallery' => MediaGalleryUpload::make('projects'),
            'payment-screenshots' => PaymentFilesUpload::make(),
            default => throw new InvalidArgumentException("Unknown media variant [{$this->variant}]."),
        };

        return $field
            ->hiddenLabel()
            // A view page never writes: show the files, allow open/download, but
            // no uploading, deleting or reordering — that lives on the Edit page.
            ->disabled()
            ->deletable(false)
            ->openable()
            ->downloadable()
            // The panel's state is client-controllable and the private disk is
            // shared by every module, so a signed URL may only ever be minted
            // for a path this record actually owns.
            ->preventFilePathTampering(allowFilePathUsing: fn (string $file): bool => in_array(
                $file,
                $this->existingFiles(),
                true,
            ))
            ->required(false);
    }

    private function field(): string
    {
        return match ($this->variant) {
            'project-gallery' => 'gallery',
            'payment-screenshots' => 'screenshots',
            default => throw new InvalidArgumentException("Unknown media variant [{$this->variant}]."),
        };
    }

    private function record(): Model
    {
        return $this->mediaRecord ??= match ($this->variant) {
            'project-gallery' => Project::query()->findOrFail($this->recordId),
            'payment-screenshots' => Payment::query()->findOrFail($this->recordId),
            default => throw new InvalidArgumentException("Unknown media variant [{$this->variant}]."),
        };
    }
}
