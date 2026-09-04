<?php

namespace App\Exports;

use App\Enums\ApprovalStatus;
use App\Exports\Concerns\StyledExportSheet;
use App\Models\Approval;
use App\Models\Requisition;
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

class RequisitionsExport implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;
    use StyledExportSheet;

    private int $rowNumber = 0;

    /** @param  Builder<Requisition>  $query */
    public function __construct(private readonly Builder $query) {}

    /** @return Builder<Requisition> */
    public function query(): Builder
    {
        return (clone $this->query)->with(['author', 'project', 'approvals.user']);
    }

    public function title(): string
    {
        return __('app.export.requisitions_sheet');
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            '№',
            __('app.label.requisition_number'),
            __('app.label.requisition_title'),
            __('app.label.description'),
            __('app.label.project_single'),
            __('app.label.author'),
            __('app.label.status'),
            __('app.approval.field.approvers'),
            __('app.approval.section'),
            __('app.approval.field.reason'),
            __('app.label.submitted'),
            __('app.label.created_at'),
        ];
    }

    /** @return array<int, mixed> */
    public function map($row): array
    {
        $active = $row->activeApprovals();

        return [
            ++$this->rowNumber,
            $row->number,
            $row->title,
            $row->description,
            $row->project?->name,
            $row->author?->name,
            $row->status->label(),
            $active->map(fn (Approval $approval): string => trim(
                '#'.$approval->order.' '.($approval->user?->name ?? __('app.label.not_set'))
            ))->implode("\n"),
            __('app.approval.progress', [
                'approved' => $active->where('status', ApprovalStatus::Approved)->count(),
                'total' => $active->count(),
            ]),
            $active->firstWhere('status', ApprovalStatus::Rejected)?->comment,
            $row->submitted_at?->format('d.m.Y H:i'),
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
                __('app.export.requisitions_title', ['year' => now()->year]),
            ),
        ];
    }
}
