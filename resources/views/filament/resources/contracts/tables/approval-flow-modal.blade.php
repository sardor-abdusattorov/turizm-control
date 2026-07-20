{{-- The approval chain as a stock Filament table widget (no custom markup):
     the full audit log of every approver row, newest processing last. --}}
@livewire(
    \App\Filament\Resources\Contracts\Widgets\ContractApproversTableWidget::class,
    ['contractId' => $contractId],
    key('contract-approvers-'.$contractId)
)
