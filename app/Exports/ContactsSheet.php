<?php

namespace App\Exports;

use App\Exports\Concerns\FormatsLocalizedValue;
use App\Exports\Concerns\StyledExportSheet;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ContactsSheet implements FromQuery, WithColumnWidths, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use FormatsLocalizedValue;
    use StyledExportSheet;

    private int $rowNumber = 0;

    /** @param  Builder<Contact>  $query */
    public function __construct(
        private readonly Builder $query,
        private readonly string $type,
    ) {}

    /** @return Builder<Contact> */
    public function query(): Builder
    {
        return (clone $this->query)
            ->where('type', $this->type)
            ->with('bankAccounts.currency')
            ->reorder('id');
    }

    public function title(): string
    {
        return $this->isLegal()
            ? __('app.export.contacts_legal_sheet')
            : __('app.export.contacts_individual_sheet');
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        if ($this->isLegal()) {
            return [
                '№',
                __('app.label.contact_name'),
                __('app.label.legal_form'),
                __('app.label.inn'),
                __('app.label.oked'),
                __('app.label.address'),
                __('app.label.phone'),
                __('app.label.email'),
                __('app.label.website'),
                __('app.label.contact_person'),
                __('app.label.director_name'),
                __('app.label.bank_account'),
                __('app.label.bank_name'),
                __('app.label.mfo').' / SWIFT',
                __('app.label.status'),
                __('app.label.created_at'),
            ];
        }

        return [
            '№',
            __('app.label.contact_name'),
            __('app.label.pinfl'),
            __('app.label.address'),
            __('app.label.phone'),
            __('app.label.email'),
            __('app.label.website'),
            __('app.label.status'),
            __('app.label.created_at'),
        ];
    }

    /** @return array<int, mixed> */
    public function map($row): array
    {
        $number = ++$this->rowNumber;
        $status = $row->status ? __('app.label.active') : __('app.label.inactive');
        $createdAt = $row->created_at?->format('d.m.Y H:i');

        if ($this->isLegal()) {
            [$accounts, $banks, $codes] = $this->bankAccountColumns($row);

            return [
                $number,
                self::localized($row->name),
                $row->legal_form,
                $row->inn,
                $row->oked,
                self::localized($row->address),
                $row->phone,
                $row->email,
                $row->website,
                $row->contact_person,
                $row->director_name,
                $accounts,
                $banks,
                $codes,
                $status,
                $createdAt,
            ];
        }

        return [
            $number,
            self::localized($row->name),
            $row->pinfl,
            self::localized($row->address),
            $row->phone,
            $row->email,
            $row->website,
            $status,
            $createdAt,
        ];
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} */
    private function bankAccountColumns(Contact $contact): array
    {
        $accounts = $contact->bankAccounts;

        if ($accounts->isEmpty()) {
            return [null, null, null];
        }

        $prefix = fn ($account): string => $accounts->count() > 1 && $account->currency
            ? $account->currency->short_name.': '
            : '';

        $banks = $accounts->map(fn ($a) => (string) $a->bank_name);
        $banks = $banks->unique()->count() === 1 ? $banks->take(1) : $banks;

        return [
            $accounts->map(fn ($a) => $prefix($a).$a->account_number)->implode("\n"),
            $banks->implode("\n"),
            $accounts->map(fn ($a) => $prefix($a).($a->mfo ?: $a->swift))->implode("\n"),
        ];
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        if ($this->isLegal()) {
            return [
                'A' => 6, 'B' => 34, 'C' => 12, 'D' => 12, 'E' => 10,
                'F' => 34, 'G' => 18, 'H' => 26, 'I' => 22, 'J' => 20,
                'K' => 22, 'L' => 32, 'M' => 28, 'N' => 16, 'O' => 12, 'P' => 17,
            ];
        }

        return [
            'A' => 6, 'B' => 34, 'C' => 17, 'D' => 34, 'E' => 18,
            'F' => 26, 'G' => 22, 'H' => 12, 'I' => 17,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        return [1 => $this->headingStyle()];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn (AfterSheet $event) => $this->applyLayout(
                $event,
                $this->isLegal()
                    ? __('app.export.contacts_legal_title', ['year' => now()->year])
                    : __('app.export.contacts_individual_title', ['year' => now()->year]),
                $this->textColumns(),
            ),
        ];
    }

    /** @return list<string> */
    private function textColumns(): array
    {
        return $this->isLegal()
            ? ['D', 'E', 'G', 'L', 'N']
            : ['C', 'E'];
    }

    private function isLegal(): bool
    {
        return $this->type === Contact::TYPE_LEGAL;
    }
}
