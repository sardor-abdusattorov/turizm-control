<?php

namespace App\Exports;

use App\Enums\PaymentSubject;
use App\Exports\Concerns\StyledExportSheet;
use App\Models\Payment;
use App\Support\Money;
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

/**
 * The payments registry export: every payment the user is allowed to see —
 * already scoped by the filtered table query — on a single sheet, sharing the
 * blue registry look with the contacts export.
 */
class PaymentsExport implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;
    use StyledExportSheet;

    private int $rowNumber = 0;

    /** @param  Builder<Payment>  $query */
    public function __construct(private readonly Builder $query) {}

    /** @return Builder<Payment> */
    public function query(): Builder
    {
        return (clone $this->query)->with(['contract', 'project', 'currency', 'creator']);
    }

    public function title(): string
    {
        return __('app.export.payments_sheet');
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            '№',
            __('app.label.payment_subject'),
            __('app.label.payment_object'),
            __('app.label.percent'),
            __('app.label.amount'),
            __('app.label.paid_at'),
            __('app.label.payment_status'),
            __('app.label.created_by'),
            __('app.label.created_at'),
        ];
    }

    /** @return array<int, mixed> */
    public function map($row): array
    {
        return [
            ++$this->rowNumber,
            ($row->isDirect() ? PaymentSubject::Project : PaymentSubject::Contract)->label(),
            $row->subjectLabel(),
            $row->isDirect() ? null : format_percent($row->percent).'%',
            $row->isDirect() ? Money::format($row->amount).' '.($row->currency?->short_name ?? '') : null,
            $row->paid_at?->format('d.m.Y'),
            $row->contract?->payment_status?->label(),
            $row->creator?->name,
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
                __('app.export.payments_title', ['year' => now()->year]),
            ),
        ];
    }
}
