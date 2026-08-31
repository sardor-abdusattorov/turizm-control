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

class MediaLibrary extends Component implements HasForms
{
    use InteractsWithForms;
    use RestrictsFileUploadsToSchemaComponents;

    #[Locked]
    public string $variant;

    #[Locked]
    public int $recordId;

    public bool $hideWhenEmpty = false;

    /** @var array<string, mixed> */
    public ?array $data = [];

    private ?Model $mediaRecord = null;

    public function mount(): void
    {
        abort_unless(Gate::allows('view', $this->record()), 403);

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

    /** @return list<string> */
    private function existingFiles(): array
    {
        $paths = (array) ($this->record()->{$this->field()} ?? []);

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

            ->disabled()
            ->deletable(false)
            ->openable()
            ->downloadable()

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
