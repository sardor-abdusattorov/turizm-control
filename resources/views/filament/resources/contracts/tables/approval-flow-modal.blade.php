
@livewire(
    \App\Filament\Resources\Contracts\Widgets\ContractApproversTableWidget::class,
    ['contractId' => $contractId],
    key('contract-approvers-'.$contractId)
)
