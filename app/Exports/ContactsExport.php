<?php

namespace App\Exports;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Splits the contacts export into one sheet per contact type so legal-entity
 * requisites and the individual PINFL never share a column layout. Only the
 * types actually present in the (filtered) query get a sheet; when the query
 * is empty both are emitted so the headers still export.
 */
class ContactsExport implements WithMultipleSheets
{
    use Exportable;

    /** @param  Builder<Contact>  $query */
    public function __construct(private readonly Builder $query) {}

    /** @return array<int, ContactsSheet> */
    public function sheets(): array
    {
        $sheets = [];

        foreach ([Contact::TYPE_LEGAL, Contact::TYPE_INDIVIDUAL] as $type) {
            if ((clone $this->query)->where('type', $type)->exists()) {
                $sheets[] = new ContactsSheet($this->query, $type);
            }
        }

        return $sheets !== [] ? $sheets : [
            new ContactsSheet($this->query, Contact::TYPE_LEGAL),
            new ContactsSheet($this->query, Contact::TYPE_INDIVIDUAL),
        ];
    }
}
