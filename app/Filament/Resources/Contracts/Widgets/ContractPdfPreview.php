<?php

namespace App\Filament\Resources\Contracts\Widgets;

use App\Models\Contract;
use Filament\Widgets\Widget;

class ContractPdfPreview extends Widget
{
    protected string $view = 'filament.resources.contracts.widgets.contract-pdf-preview';

    protected int|string|array $columnSpan = 'full';

    public ?Contract $record = null;

    public function getPdfUrl(): ?string
    {
        if (! $this->record || ! $this->record->documentExists()) {
            return null;
        }

        if (! in_array($this->record->status, [
            Contract::STATUS_IN_REVIEW,
            Contract::STATUS_APPROVED,
        ], true)) {
            return null;
        }

        return route('contracts.pdf.inline', ['contract' => $this->record]);
    }
}
