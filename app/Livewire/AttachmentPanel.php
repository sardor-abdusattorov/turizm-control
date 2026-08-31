<?php

namespace App\Livewire;

use App\Enums\PressTourAttachmentType;
use App\Filament\Support\ContractDossierUpload;
use App\Filament\Support\PressTourDocumentsUpload;
use App\Models\Contract;
use App\Models\PressTour;
use App\Services\Documents\SyncAttachments;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AttachmentPanel extends Component implements HasForms
{
    use InteractsWithForms {
        isFileUploadForSchemaComponent as private schemaOwnsFileUpload;
    }
    use RestrictsFileUploadsToSchemaComponents;

    #[Locked]
    public string $variant;

    #[Locked]
    public int $recordId;

    /** @var array<string, mixed> */
    public ?array $data = [];

    private ?Model $panelRecord = null;

    public function mount(): void
    {
        abort_unless(Gate::allows('view', $this->record()), 403);

        $this->form->fill($this->currentState());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(array_values(array_filter([
                $this->typeField(),
                $this->uploadField(),
            ])))
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(Gate::allows('view', $this->record()), 403);
        abort_unless($this->canManage(), 403);

        $data = $this->form->getState();

        abort_unless(array_key_exists($this->field(), $data), 409);

        app(SyncAttachments::class)->sync(
            $this->relation(),
            (array) $data[$this->field()],
            (array) ($data[$this->namesField()] ?? []),
            $this->attributesForNewFiles($data),
        );

        $this->form->fill($this->currentState());

        Notification::make()->title(__('app.message.attachments_uploaded'))->success()->send();

        $this->dispatch('attachments-saved');
    }

    public function isFileUploadForSchemaComponent(string $name): bool
    {
        return $this->canManage() && $this->schemaOwnsFileUpload($name);
    }

    public function canManage(): bool
    {
        return match ($this->variant) {
            'contract-dossier' => $this->contract()->attachmentsManageableBy(),
            'press-tour-documents' => Gate::allows('update', $this->record()),
            default => false,
        };
    }

    public function lockedNotice(): ?string
    {
        if ($this->canManage() || $this->variant !== 'contract-dossier') {
            return null;
        }

        return $this->contract()->documentEditWouldResetApprovals()
            ? __('app.message.dossier_frozen_in_review')
            : null;
    }

    public function headerIcon(): string
    {
        return 'heroicon-o-paper-clip';
    }

    public function headerTitle(): string
    {
        return match ($this->variant) {
            'contract-dossier' => __('app.label.attachments'),
            'press-tour-documents' => __('app.label.press_tour_documents'),
            default => __('app.label.attachments'),
        };
    }

    public function render(): View
    {
        return view('livewire.attachment-panel');
    }

    private function uploadField(): FileUpload
    {
        $field = match ($this->variant) {
            'contract-dossier' => ContractDossierUpload::make($this->field()),
            'press-tour-documents' => PressTourDocumentsUpload::make($this->field()),
            default => throw new InvalidArgumentException("Unknown attachment variant [{$this->variant}]."),
        };

        $canManage = $this->canManage();

        return $field
            ->hiddenLabel()

            ->disabled(! $canManage)
            ->deletable($canManage)
            ->openable()
            ->downloadable()

            ->preventFilePathTampering(allowFilePathUsing: fn (string $file): bool => $this->relation()
                ->where('file_path', $file)
                ->exists())
            ->columnSpanFull();
    }

    private function typeField(): ?Select
    {
        if ($this->variant !== 'press-tour-documents' || ! $this->canManage()) {
            return null;
        }

        return Select::make('type')
            ->label(__('app.label.attachment_type'))
            ->options(PressTourAttachmentType::options())
            ->default(PressTourAttachmentType::Report->value)
            ->selectablePlaceholder(false)
            ->helperText(__('app.helper.attachment_type_applies_to_new'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributesForNewFiles(array $data): array
    {
        return $this->variant === 'press-tour-documents'
            ? ['type' => $data['type'] ?? PressTourAttachmentType::Report->value]
            : [];
    }

    /** @return array<string, mixed> */
    private function currentState(): array
    {
        $attachments = $this->relation()->get();

        return [

            $this->field() => $attachments
                ->filter(fn (Model $attachment): bool => Storage::disk('local')->exists((string) $attachment->file_path))
                ->pluck('file_path')
                ->values()
                ->all(),
            $this->namesField() => $attachments->pluck('original_name', 'file_path')->all(),
        ];
    }

    private function field(): string
    {
        return match ($this->variant) {
            'contract-dossier' => 'attachment_files',
            'press-tour-documents' => 'document_files',
            default => throw new InvalidArgumentException("Unknown attachment variant [{$this->variant}]."),
        };
    }

    private function namesField(): string
    {
        return match ($this->variant) {
            'contract-dossier' => ContractDossierUpload::namesField($this->field()),
            'press-tour-documents' => PressTourDocumentsUpload::namesField($this->field()),
            default => throw new InvalidArgumentException("Unknown attachment variant [{$this->variant}]."),
        };
    }

    /** @return HasMany<Model, Model> */
    private function relation(): HasMany
    {
        return $this->record()->attachments();
    }

    private function contract(): Contract
    {
        $record = $this->record();

        return $record instanceof Contract
            ? $record
            : throw new InvalidArgumentException('The dossier variant expects a contract.');
    }

    private function record(): Model
    {
        return $this->panelRecord ??= match ($this->variant) {
            'contract-dossier' => Contract::query()->findOrFail($this->recordId),
            'press-tour-documents' => PressTour::query()->findOrFail($this->recordId),
            default => throw new InvalidArgumentException("Unknown attachment variant [{$this->variant}]."),
        };
    }
}
