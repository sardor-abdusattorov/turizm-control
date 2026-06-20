<?php

namespace App\Exports;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
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

class ContactsExport implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;

    /**
     * Indexes of columns whose value must be forced to text in Excel so that
     * leading zeros on INN / PINFL / OKED / bank account / MFO are not lost
     * when Excel auto-detects them as numbers.
     */
    private const TEXT_COLUMNS = ['E', 'F', 'G', 'M', 'O'];

    /** @param  Builder<Contact>  $query */
    public function __construct(private readonly Builder $query) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function title(): string
    {
        return __('app.export.contacts_sheet_title');
    }

    public function headings(): array
    {
        return [
            '№',
            __('app.label.contact_type'),
            __('app.label.contact_name'),
            __('app.label.legal_form'),
            __('app.label.inn'),
            __('app.label.pinfl'),
            'OKED',
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

    /** @return array<int, mixed> */
    public function map($row): array
    {
        return [
            $row->id,
            $row->type === Contact::TYPE_LEGAL
                ? __('app.contact.type.legal')
                : __('app.contact.type.individual'),
            self::localized($row->name),
            $row->legal_form,
            $row->inn,
            $row->pinfl,
            $row->oked,
            self::localized($row->address),
            $row->phone,
            $row->email,
            $row->contact_person,
            $row->director_name,
            $row->bank_account,
            $row->bank_name,
            $row->mfo,
            $row->status ? __('app.label.active') : __('app.label.inactive'),
            $row->created_at?->format('d.m.Y H:i'),
        ];
    }

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

                // Title above the headings.
                $sheet->insertNewRowBefore(1, 1);
                $titleRange = "A1:{$lastColumn}1";
                $sheet->mergeCells($titleRange);
                $sheet->setCellValue('A1', __('app.export.contacts_title', ['year' => now()->year]));
                $sheet->getStyle($titleRange)->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Times New Roman', 'size' => 14],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(28);

                // Body font + borders.
                $tableRange = "A2:{$lastColumn}".($lastRow + 1);
                $sheet->getStyle($tableRange)->getFont()->setName('Times New Roman')->setSize(11);
                $sheet->getStyle($tableRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '808080']],
                    ],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                ]);

                // Force INN / PINFL / OKED / bank account / MFO to text so Excel
                // doesn't reformat them and strip leading zeros.
                foreach (self::TEXT_COLUMNS as $column) {
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

    private static function localized(mixed $value): ?string
    {
        if (is_array($value)) {
            return $value[app()->getLocale()] ?? $value['ru'] ?? (reset($value) ?: null);
        }

        return $value;
    }
}
