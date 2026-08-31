<?php

namespace App\Exports;

use App\Enums\PressTourDirection;
use App\Models\PressTour;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PressToursExport implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    use Exportable;

    private const LAST_COLUMN = 'K';

    /** @var list<list<mixed>> */
    private array $rows = [];

    private array $sectionRows = [];

    /** @param  Builder<PressTour>  $query */
    public function __construct(private readonly Builder $query) {}

    public function title(): string
    {
        return __('app.export.press_tours_sheet');
    }

    /** @return list<list<mixed>> */
    public function array(): array
    {
        $tours = (clone $this->query)
            ->with('order')
            ->withCount('attachments')
            ->get();

        $this->rows = [[
            '№',
            __('app.label.press_tour_name'),
            __('app.label.press_tour_place'),
            __('app.label.press_tour_period'),
            __('app.label.press_tour_people'),
            __('app.label.responsible'),
            __('app.label.press_tour_foreign_partner'),
            __('app.label.press_tour_notes'),
            __('app.label.press_tour_state'),
            __('app.label.press_tour_held_on'),
            __('app.label.press_tour_documents'),
        ]];

        $number = 0;

        foreach (PressTourDirection::cases() as $direction) {
            $section = $tours->where('direction', $direction);

            if ($section->isEmpty()) {
                continue;
            }

            $this->sectionRows[] = count($this->rows) - 1;
            $this->rows[] = [__('app.export.press_tours_section_'.$direction->value), '', '', '', '', '', '', '', '', '', ''];

            foreach ($section as $tour) {
                $this->rows[] = [
                    ++$number,
                    $tour->name,
                    $tour->place,
                    $tour->period,

                    $tour->peopleLabel(),

                    implode("\n", $tour->responsibleNames()),
                    $tour->foreign_partner,
                    $tour->notes,
                    $tour->state?->label(),
                    $tour->held_on?->format('d.m.Y'),
                    $tour->attachments_count ?: null,
                ];
            }
        }

        return $this->rows;
    }

    /** @return array<string, float> */
    public function columnWidths(): array
    {
        return [
            'A' => 5, 'B' => 42, 'C' => 16, 'D' => 15, 'E' => 9,
            'F' => 20, 'G' => 24, 'H' => 26, 'I' => 14, 'J' => 14, 'K' => 12,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $last = self::LAST_COLUMN;
                $lastRow = $sheet->getHighestRow();

                $sheet->insertNewRowBefore(1, 1);
                $sheet->mergeCells("A1:{$last}1");
                $sheet->setCellValue('A1', __('app.export.press_tours_title', ['year' => now()->year]));
                $sheet->getStyle("A1:{$last}1")->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Times New Roman', 'size' => 14],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(28);

                $tableRange = "A2:{$last}".($lastRow + 1);
                $sheet->getStyle($tableRange)->getFont()->setName('Times New Roman')->setSize(11);
                $sheet->getStyle($tableRange)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '808080']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                ]);

                $sheet->getStyle("A2:{$last}2")->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Times New Roman', 'size' => 12],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
                ]);

                foreach ($this->sectionRows as $index) {
                    $row = $index + 3;
                    $sheet->mergeCells("A{$row}:{$last}{$row}");
                    $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'name' => 'Times New Roman', 'size' => 12],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EDF2F9']],
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(22);
                }

                $sheet->getStyle('A3:A'.($lastRow + 1))
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('E3:E'.($lastRow + 1))
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("I3:{$last}".($lastRow + 1))
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->freezePane('A3');
            },
        ];
    }
}
