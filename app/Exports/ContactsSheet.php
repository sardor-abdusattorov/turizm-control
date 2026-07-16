<?php

namespace App\Exports;

use App\Exports\Concerns\StyledExportSheet;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One worksheet of the contacts export, scoped to a single contact type so a
 * column only ever appears where it applies: legal entities carry their
 * requisites (legal form, INN, OKED, director, bank details), individuals only
 * their PINFL. Shares the blue registry look with its sibling sheet.
 */
class ContactsSheet implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
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
            ->with('bankAccounts')
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
                __('app.label.mfo'),
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
            $account = $row->bankAccountFor();

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
                $account?->account_number,
                $account?->bank_name,
                $account?->mfo,
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

    /**
     * Columns whose digit strings must stay text so Excel keeps leading zeros
     * and skips scientific notation: identifiers, phone and bank requisites.
     *
     * @return list<string>
     */
    private function textColumns(): array
    {
        return $this->isLegal()
            ? ['D', 'E', 'G', 'L', 'N'] // INN, OKED, phone, account number, MFO
            : ['C', 'E'];               // PINFL, phone
    }

    private function isLegal(): bool
    {
        return $this->type === Contact::TYPE_LEGAL;
    }

    private static function localized(mixed $value): ?string
    {
        if (is_array($value)) {
            return $value[app()->getLocale()] ?? $value['ru'] ?? (reset($value) ?: null);
        }

        return $value;
    }
}
