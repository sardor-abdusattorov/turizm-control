<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Payments\PaymentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected string $view = 'filament.resources.payments.pages.view-payment';

    public function getHeading(): string
    {
        return format_percent((float) $this->record->percent)
            .'% · '.($this->record->contract?->number ?? __('app.label.payment_single'));
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function contractUrl(): ?string
    {
        return $this->record->contract
            ? ContractResource::getUrl('view', ['record' => $this->record->contract])
            : null;
    }

    /**
     * @return list<array{url: string, name: string, pdf: bool}>
     */
    public function screenshotFiles(): array
    {
        return $this->record->screenshotFiles();
    }
}
