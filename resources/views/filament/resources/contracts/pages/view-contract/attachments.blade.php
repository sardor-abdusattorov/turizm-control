{{-- Attachments — the contract dossier as Filament's own FileUpload panel:
     signed scan, buyruq copy, proposals, invoice, SWIFT slip, act, fees.
     Uploading, removing and reordering freeze mid-approval
     (Contract::attachmentsManageableBy), which locks the panel. --}}
<div x-show="tab === 'attachments'" x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 -translate-y-1"
     x-transition:enter-end="opacity-100 translate-y-0"
     class="cw-panel">
    @livewire(\App\Livewire\AttachmentPanel::class, ['variant' => 'contract-dossier', 'recordId' => $record->id], key('contract-dossier-'.$record->id))
</div>
