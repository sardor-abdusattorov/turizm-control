{{-- Attachments — the contract dossier as a stock Filament table widget:
     signed scan, buyruq copy, proposals, invoice, SWIFT slip, act, fees.
     Upload/delete freeze mid-approval (Contract::attachmentsManageableBy). --}}
<div x-show="tab === 'attachments'" x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 -translate-y-1"
     x-transition:enter-end="opacity-100 translate-y-0"
     class="cw-panel">
    @livewire(\App\Filament\Resources\Contracts\Widgets\ContractAttachmentsTableWidget::class, ['contractId' => $record->id], key('contract-attachments-'.$record->id))
</div>
