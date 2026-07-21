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
            // Attach the record so preventFilePathTampering() can authorise the
            // already-stored paths against the DB value on every save.
            ->record($this->record())
            ->statePath('data');
    }

    /**
     * Persist the field back to the record. Fired by the FileUpload itself on
     * every change (add / reorder / remove) so there is no separate save step.
     */
    public function save(): void
    {
        $record = $this->record();

        if (! auth()->user()?->can('update', $record)) {
            return;
        }

        $state = $this->form->getState();

        $record->update([
            $this->field() => array_values(array_filter(
                (array) ($state[$this->field()] ?? []),
                fn (mixed $path): bool => is_string($path) && $path !== '',
            )),
        ]);
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
            ->preventFilePathTampering()
            ->disabled(! $this->canEdit)
            ->live()
            ->afterStateUpdated(fn () => $this->save());
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
