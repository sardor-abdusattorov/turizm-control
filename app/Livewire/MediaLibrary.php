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
use InvalidArgumentException;
use Livewire\Component;

/**
 * Inline media library backed by Filament's own FileUpload — the same
 * component the edit forms use, embedded straight into a view page so photos,
 * videos and payment proofs are added, reordered and removed in place, with a
 * live grid preview. Replaces the hand-rolled gallery/screenshot markup.
 *
 * Two variants share the plumbing: the project gallery (`project-gallery`) and
 * the payment proofs (`payment-screenshots`). Each resolves its own record and
 * upload field; editing is gated by the record's `update` policy.
 */
class MediaLibrary extends Component implements HasForms
{
    use InteractsWithForms;
    use RestrictsFileUploadsToSchemaComponents;

    public string $variant;

    public int $recordId;

    public bool $canEdit = false;

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    private ?Model $mediaRecord = null;

    /** Guards save() against the re-entrant call getState() triggers. */
    private bool $isPersisting = false;

    public function mount(): void
    {
        $record = $this->record();

        $this->canEdit = (bool) auth()->user()?->can('update', $record);

        $this->form->fill([
            $this->field() => $record->{$this->field()} ?? [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([$this->uploadField()])
            ->statePath('data');
    }

    /**
     * Persist the field back to the record. Fired by the FileUpload itself on
     * every change (add / reorder / remove) so there is no separate save step.
     */
    public function save(): void
    {
        // getState() re-stores fresh uploads and fires this field's
        // afterStateUpdated again; without this guard a single upload would
        // recurse save() → getState() → save() … indefinitely.
        if ($this->isPersisting) {
            return;
        }

        $record = $this->record();

        if (! auth()->user()?->can('update', $record)) {
            return;
        }

        $this->isPersisting = true;

        try {
            $state = $this->form->getState();

            $record->update([
                $this->field() => array_values(array_filter(
                    (array) ($state[$this->field()] ?? []),
                    fn (mixed $path): bool => is_string($path) && $path !== '',
                )),
            ]);
        } finally {
            $this->isPersisting = false;
        }
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
            // On the view page there is no "submit" — an empty set is a valid
            // (fully cleared) state, unlike the create form which requires one.
            ->required(false)
            // Lock the field down to files this component legitimately owns:
            // the paths already stored on the record, plus anything under the
            // media directory it uploads into (a file just added this session
            // is persisted before auto-save writes it to the record, so it is
            // not yet in the record's own set). Everything else — a tampered
            // path pointing at another disk area — is rejected. A standalone
            // Livewire schema does not carry its record down to
            // getOriginalFilePaths(), so this callback is the whole check.
            ->preventFilePathTampering(
                allowFilePathUsing: fn (string $file): bool => in_array($file, $this->currentPaths(), true)
                    || str_starts_with($file, $this->uploadDirectoryPrefix()),
            )
            ->disabled(! $this->canEdit)
            ->live()
            ->afterStateUpdated(fn () => $this->save());
    }

    /**
     * The paths currently persisted on the record — the allow-list a submitted
     * string path is checked against.
     *
     * @return list<string>
     */
    private function currentPaths(): array
    {
        return array_values(array_filter(
            (array) ($this->record()->{$this->field()} ?? []),
            fn (mixed $path): bool => is_string($path),
        ));
    }

    private function field(): string
    {
        return match ($this->variant) {
            'project-gallery' => 'gallery',
            'payment-screenshots' => 'screenshots',
            default => throw new InvalidArgumentException("Unknown media variant [{$this->variant}]."),
        };
    }

    /**
     * The directory (on the private disk) this component's uploads live under —
     * the boundary a freshly-added file is authorised against before auto-save
     * records it. Mirrors the directories in MediaGalleryUpload / PaymentFilesUpload.
     */
    private function uploadDirectoryPrefix(): string
    {
        return match ($this->variant) {
            'project-gallery' => 'uploads/images/projects/',
            'payment-screenshots' => 'uploads/images/'.Payment::SCREENSHOT_DIR.'/',
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
