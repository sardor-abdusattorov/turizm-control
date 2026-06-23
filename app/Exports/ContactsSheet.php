<?php

namespace App\Exports;

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
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One worksheet of the contacts export, scoped to a single contact type so a
 * column only ever appears where it applies: legal entities carry their
 * requisites (legal form, INN, OKED, director, bank details), individuals only
 * their PINFL. Shares the blue header, borders and text-formatted identifier
 * columns with its sibling sheet.
 */
class ContactsSheet implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
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
            return [
                $number,
                self::localized($row->name),
                $row->legal_form,
                $row->inn,
                $row->oked,
                self::localized($row->address),
                $row->phone,
                $row->email,
                $row->contact_person,
                $row->director_name,
                $row->bank_account,
                $row->bank_name,
                $row->mfo,
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
            $status,
            $createdAt,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'name' => 'Times New Roman', 'size' => 12],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D9E1F2'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                $sheet->insertNewRowBefore(1, 1);
                $titleRange = "A1:{$lastColumn}1";
                $sheet->mergeCells($titleRange);
                $sheet->setCellValue('A1', $this->isLegal()
                    ? __('app.export.contacts_legal_title', ['year' => now()->year])
                    : __('app.export.contacts_individual_title', ['year' => now()->year]));
                $sheet->getStyle($titleRange)->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Times New Roman', 'size' => 14],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(28);

                $tableRange = "A2:{$lastColumn}".($lastRow + 1);
                $sheet->getStyle($tableRange)->getFont()->setName('Times New Roman')->setSize(11);
                $sheet->getStyle($tableRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '808080']],
                    ],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                ]);

                foreach ($this->textColumns() as $column) {
                    $sheet->getStyle("{$column}3:{$column}".($lastRow + 1))
                        ->getNumberFormat()
                        ->setFormatCode('@');

                    for ($row = 3; $row <= $lastRow + 1; $row++) {
                        $value = $sheet->getCell("{$column}{$row}")->getValue();

                        if ($value !== null && $value !== '') {
                            $sheet->getCell("{$column}{$row}")
                                ->setValueExplicit((string) $value, DataType::TYPE_STRING);
                        }
                    }
                }

                $sheet->freezePane('A3');
            },
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
            ? ['D', 'E', 'G', 'K', 'M'] // INN, OKED, phone, account number, MFO
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
