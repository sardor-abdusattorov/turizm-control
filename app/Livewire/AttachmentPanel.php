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
use Livewire\Component;

/**
 * A record's file dossier as Filament's own FileUpload panel — drag-and-drop,
 * thumbnails, reorder, open and download, all in the component the edit forms
 * already use. It replaces the hand-built file tables: files are files, not
 * rows of metadata.
 *
 * Two variants share the plumbing, both backed by a HasMany of attachment
 * rows kept in step by SyncAttachments: the contract dossier
 * (`contract-dossier`) and a press tour's report pack
 * (`press-tour-documents`).
 *
 * A viewer who may not manage the files gets the same panel, locked.
 */
class AttachmentPanel extends Component implements HasForms
{
    use InteractsWithForms;
    use RestrictsFileUploadsToSchemaComponents;

    public string $variant;

    public int $recordId;

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    private ?Model $panelRecord = null;

    public function mount(): void
    {
        // $recordId arrives from the client, so the panel carries its own view
        // gate rather than trusting the page that embedded it — otherwise any
        // signed-in user could mount it against someone else's record and read
        // the file list (and its signed URLs).
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

    /**
     * The submitted panel IS the dossier: new chips are filed and removed
     * chips take their attachment — and its file — with them.
     */
    public function save(): void
    {
        // The button is merely hidden on a locked panel; save() is a public
        // Livewire method anyone can call, so the gate lives here too.
        abort_unless($this->canManage(), 403);

        $data = $this->form->getState();

        app(SyncAttachments::class)->sync(
            $this->relation(),
            (array) ($data[$this->field()] ?? []),
            (array) ($data[$this->namesField()] ?? []),
            $this->attributesForNewFiles($data),
        );

        // Re-fill from the database: freshly stored paths replace the upload
        // objects, so a second save does not re-file the same files.
        $this->form->fill($this->currentState());

        Notification::make()->title(__('app.message.attachments_uploaded'))->success()->send();

        $this->dispatch('attachments-saved');
    }

    public function canManage(): bool
    {
        return match ($this->variant) {
            'contract-dossier' => $this->contract()->attachmentsManageableBy(),
            'press-tour-documents' => Gate::allows('update', $this->record()),
            default => false,
        };
    }

    /**
     * Why the panel is locked, when the reason is worth saying out loud. A
     * contract dossier freezes mid-approval so approvers review a fixed set
     * of files; missing permission needs no explanation.
     */
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
            // Locked panels still show the pack and let a viewer open or
            // download it — only writing is off.
            ->disabled(! $canManage)
            ->deletable($canManage)
            ->openable()
            ->downloadable()
            ->columnSpanFull();
    }

    /**
     * A tour's pack is catalogued — report, media coverage, photos, programme,
     * participant list, act — so the kind is picked once for the batch being
     * added. A contract dossier has no such question: files are just files.
     */
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

    /**
     * @return array<string, mixed>
     */
    private function currentState(): array
    {
        $attachments = $this->relation()->get();

        return [
            // Only feed the panel files that actually exist on disk: a stale
            // path would render as an empty, broken tile — and worse, its
            // absence from the next submit would read as a removal.
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

    /**
     * @return HasMany<Model, Model>
     */
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
