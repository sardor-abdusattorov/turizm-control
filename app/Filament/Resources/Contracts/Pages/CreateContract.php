<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Schemas\ContractForm;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Facades\Auth;

class CreateContract extends CreateRecord
{
    use HasWizard;

    protected static string $resource = ContractResource::class;

    /**
     * @var array<int, array{user_id: int}>
     */
    protected array $pendingApprovers = [];

    protected function getSteps(): array
    {
        return [
            Step::make(__('app.label.contract_step_basic'))
                ->icon('heroicon-o-document-text')
                ->schema(ContractForm::basicSchema()),

            Step::make(__('app.label.contract_step_approvers'))
                ->icon('heroicon-o-users')
                ->description(__('app.helper.contract_step_approvers'))
                ->schema(ContractForm::approversSchema()),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['responsible_id'] = Auth::id();

        // Approvers are stored in a separate table, not on the contracts
        // row. Stash them so afterCreate() can attach them once the
        // contract has an id.
        $this->pendingApprovers = $data['approvers'] ?? [];
        unset($data['approvers']);

        return $data;
    }

    protected function afterCreate(): void
    {
        foreach ($this->pendingApprovers as $index => $approver) {
            $userId = $approver['user_id'] ?? null;

            if (! $userId) {
                continue;
            }

            $this->record->approvers()->create([
                'user_id' => $userId,
                'order' => $index + 1,
            ]);
        }
    }
}
