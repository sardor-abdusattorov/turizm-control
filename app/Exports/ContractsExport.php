<?php

namespace App\Exports;

use App\Models\Contract;
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
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ContractsExport implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;

    /**
     * @param  Builder<Contract>  $query  Already-filtered query straight off
     *                                    the Filament table — keeps the export
     *                                    in sync with what the user is seeing.
     */
    public function __construct(private readonly Builder $query) {}

    public function query(): Builder
    {
        return $this->query->with(['contact', 'currency', 'responsible']);
    }

    public function title(): string
    {
        return __('app.export.contracts_sheet_title');
    }

    public function headings(): array
    {
        return [
            '№',
            __('app.label.contract_number'),
            __('app.label.contract_title'),
            __('app.label.contact_single'),
            __('app.label.amount'),
            __('app.label.currency_single'),
            __('app.label.status'),
            __('app.label.payment_status'),
            __('app.label.paid_percent'),
            __('app.label.responsible'),
            __('app.label.signing_date'),
            __('app.label.created_at'),
        ];
    }

    /** @return array<int, mixed> */
    public function map($row): array
    {
        return [
            $row->id,
            $row->number,
            $row->title,
            self::localized($row->contact?->name),
            (float) $row->amount,
            $row->currency?->short_name,
            $row->status?->label(),
            $row->payment_status?->label(),
            (float) $row->paid_percent,
            $row->responsible?->name,
            $row->signed_at?->format('d.m.Y'),
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

                $sheet->insertNewRowBefore(1, 1);
                $titleRange = "A1:{$lastColumn}1";
                $sheet->mergeCells($titleRange);
                $sheet->setCellValue('A1', __('app.export.contracts_title', ['year' => now()->year]));
                $sheet->getStyle($titleRange)->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Times New Roman', 'size' => 14],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(28);

                $bodyRange = "A2:{$lastColumn}".($lastRow + 1);
                $sheet->getStyle($bodyRange)->getFont()->setName('Times New Roman')->setSize(11);

                $tableRange = "A2:{$lastColumn}".($lastRow + 1);
                $sheet->getStyle($tableRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '808080']],
                    ],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                ]);

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
