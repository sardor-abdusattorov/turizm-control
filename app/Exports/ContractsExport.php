<?php

namespace App\Exports;

use App\Exports\Concerns\FormatsLocalizedValue;
use App\Exports\Concerns\StyledExportSheet;
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
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ContractsExport implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;
    use FormatsLocalizedValue;
    use StyledExportSheet;

    private int $rowNumber = 0;

    /** @param  Builder<Contract>  $query  Already-filtered query straight off */
    public function __construct(private readonly Builder $query) {}

    public function query(): Builder
    {
        return (clone $this->query)
            ->with(['contact', 'sponsor', 'currency', 'responsible', 'contractType', 'project']);
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
            __('app.label.contract_type_single'),
            __('app.label.project_single'),
            __('app.label.counterparty'),
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
            ++$this->rowNumber,
            $row->number,
            $row->title,
            $row->contractType?->title,
            $row->project?->name,
            self::localized($row->counterparty()?->name),
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
                __('app.export.contracts_title', ['year' => now()->year]),
            ),
        ];
    }
}
