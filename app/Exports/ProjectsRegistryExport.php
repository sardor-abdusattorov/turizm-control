<?php

namespace App\Exports;

use App\Enums\ProjectType;
use App\Models\Contract;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProjectsRegistryExport implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    use Exportable;

    private array $blocks = [];

    private array $totalRows = [];

    private array $rows = [];

    private string $registryTitle = '';

    private const MONTHS = [
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
        5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
        9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
    ];

    private const CURRENCY_LABELS = [
        'EUR' => 'EURO',
        'GBP' => 'Фунт ст',
        'UZS' => 'Сум',
    ];

    /** @param  Builder<Project>  $query */
    public function __construct(
        private readonly Builder $query,
        private readonly ProjectType $type,
    ) {}

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        $projects = (clone $this->query)
            ->with(['incomeContracts.currency', 'incomeContracts.contact', 'incomeContracts.sponsor', 'areaCurrency', 'standCurrency'])
            ->reorder('starts_on')
            ->get();

        $this->registryTitle = $this->buildTitle($projects);

        $this->rows = [[
            '№',
            __('app.export.registry_col_name'),
            __('app.export.registry_col_dates'),
            __('app.export.registry_col_area'),
            __('app.export.registry_col_area_cost'),
            __('app.export.registry_col_currency'),
            __('app.export.registry_col_stand_cost'),
            __('app.export.registry_col_currency'),
            __('app.export.registry_col_participants'),
            __('app.export.registry_col_amount'),
            __('app.export.registry_col_currency'),
            __('app.export.registry_col_profit'),
        ]];

        foreach ($projects as $index => $project) {
            $income = $project->incomeContracts
                ->reject(fn (Contract $contract): bool => $contract->status === Contract::STATUS_REJECTED)
                ->sortBy('id')
                ->values();
            $span = max(1, $income->count());
            $start = count($this->rows) - 1;

            for ($i = 0; $i < $span; $i++) {
                $contract = $income[$i] ?? null;
                $counterparty = $contract?->contact?->name ?? $contract?->sponsor?->name;

                $this->rows[] = $i === 0
                    ? [
                        $index + 1,
                        $project->name,
                        $this->formatDates($project),
                        $this->formatArea($project),
                        $project->area_is_free ? __('app.export.registry_free') : ($project->area_cost !== null ? (float) $project->area_cost : null),
                        $project->area_is_free ? null : $this->currencyLabel($project->areaCurrency?->short_name),
                        $project->stand_cost !== null ? (float) $project->stand_cost : null,
                        $this->currencyLabel($project->standCurrency?->short_name),
                        $counterparty,
                        $contract !== null ? (float) $contract->amount : null,
                        $this->currencyLabel($income->first()?->currency?->short_name ?? 'UZS'),
                        $project->feesTotal(),
                    ]
                    : [
                        null, null, null, null, null, null, null, null,
                        $counterparty,
                        $contract !== null ? (float) $contract->amount : null,
                        null, null,
                    ];
            }

            $this->blocks[] = ['start' => $start, 'span' => $span];

            $this->rows[] = [__('app.export.registry_total'), null, null, null, null, null, null, null, null, null, null, null];
            $this->totalRows[] = count($this->rows) - 2;
        }

        return $this->rows;
    }

    public function title(): string
    {
        return __('app.label.projects');
    }

    /** @return array<string, float> */
    public function columnWidths(): array
    {
        return [
            'A' => 5, 'B' => 27.6, 'C' => 16.9, 'D' => 15.7, 'E' => 17.4, 'F' => 10.1,
            'G' => 16.7, 'H' => 11.3, 'I' => 28.3, 'J' => 17.7, 'K' => 10.4, 'L' => 15.6,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                $sheet->insertNewRowBefore(1, 1);
                $sheet->mergeCells('A1:L1');
                $sheet->setCellValue('A1', $this->registryTitle);
                $sheet->getStyle('A1:L1')->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Times New Roman', 'size' => 14],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(28);

                $tableRange = 'A2:L'.($lastRow + 1);
                $sheet->getStyle($tableRange)->getFont()->setName('Times New Roman')->setSize(11);
                $sheet->getStyle($tableRange)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '808080']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                ]);

                $sheet->getStyle('A2:L2')->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Times New Roman', 'size' => 12],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
                ]);

                $sheet->getStyle('E3:L'.($lastRow + 1))->getNumberFormat()->setFormatCode('#,##0');

                foreach ($this->blocks as $block) {
                    $top = $block['start'] + 3;
                    $bottom = $top + $block['span'] - 1;

                    if ($bottom > $top) {
                        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'K', 'L'] as $column) {
                            $sheet->mergeCells("{$column}{$top}:{$column}{$bottom}");
                        }
                    }
                }

                foreach ($this->totalRows as $index) {
                    $row = $index + 3;
                    $sheet->mergeCells("A{$row}:C{$row}");
                    $sheet->mergeCells("D{$row}:E{$row}");
                    $sheet->mergeCells("K{$row}:L{$row}");
                    $sheet->getStyle("A{$row}")->getFont()->setBold(true);
                }

                $sheet->freezePane('A3');
            },
        ];
    }

    /** @param  Collection<int, Project>  $projects */
    private function buildTitle(Collection $projects): string
    {
        $years = $projects->pluck('starts_on')->filter()->map(fn ($date) => $date->year)->unique()->sort()->values();
        $year = $years->isEmpty() ? (string) now()->year : $years->implode('–');

        return $this->type === ProjectType::International
            ? __('app.export.registry_title_international', ['year' => $year])
            : __('app.export.registry_title_internal', ['year' => $year]);
    }

    private function formatDates(Project $project): ?string
    {
        if (! $project->starts_on) {
            return null;
        }

        $start = $project->starts_on;
        $end = $project->ends_on;

        if (! $end || $start->isSameDay($end)) {
            return $start->day.'-'.self::MONTHS[$start->month];
        }

        if ($start->month === $end->month) {
            return $start->day.'-'.$end->day.'-'.self::MONTHS[$start->month];
        }

        return $start->day.'-'.self::MONTHS[$start->month].'-'.$end->day.'-'.self::MONTHS[$end->month];
    }

    private function formatArea(Project $project): ?string
    {
        if ($project->area_sqm === null) {
            return null;
        }

        $value = rtrim(rtrim(number_format((float) $project->area_sqm, 2, ',', ''), '0'), ',');

        return $value.'м2';
    }

    private function currencyLabel(?string $shortName): ?string
    {
        if ($shortName === null) {
            return null;
        }

        return self::CURRENCY_LABELS[$shortName] ?? $shortName;
    }
}
