<?php

namespace App\Exports\Concerns;

use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

trait StyledExportSheet
{
    /** @return array<string, mixed> The bold blue heading-row style. */
    private function headingStyle(): array
    {
        return [
            'font' => ['bold' => true, 'name' => 'Times New Roman', 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D9E1F2'],
            ],
        ];
    }

    /** @param  list<string>  $textColumns */
    private function applyLayout(AfterSheet $event, string $title, array $textColumns = []): void
    {
        $sheet = $event->sheet->getDelegate();
        $lastColumn = $sheet->getHighestColumn();
        $lastRow = $sheet->getHighestRow();

        $sheet->insertNewRowBefore(1, 1);
        $titleRange = "A1:{$lastColumn}1";
        $sheet->mergeCells($titleRange);
        $sheet->setCellValue('A1', $title);
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

        foreach ($textColumns as $column) {
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
    }
}
