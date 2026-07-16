<?php

namespace App\Filament\Resources\Contacts\Pages;

use App\Filament\Resources\Contacts\ContactResource;
use App\Models\Contract;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Collection;

class ViewContact extends ViewRecord
{
    protected static string $resource = ContactResource::class;

    protected string $view = 'filament.resources.contacts.pages.view-contact';

    public function getHeading(): string
    {
        return $this->record->getTranslation('name', app()->getLocale());
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    /**
     * The counterparty's contracts this viewer may see — same visibleTo rule
     * as everywhere, so a manager without oversight only sees their own.
     *
     * @return Collection<int, Contract>
     */
    public function contracts(): Collection
    {
        return $this->record->visibleContracts();
    }

    /**
     * Every project this counterparty took part in, newest first, with the
     * project and currency ready for the table.
     *
     * @return Collection<int, Contract>
     */
    public function participations(): Collection
    {
        return $this->record->incomeContracts()
            ->visibleTo()
            ->with(['project', 'currency', 'contractType'])
            ->latest('id')
            ->get();
    }
}
