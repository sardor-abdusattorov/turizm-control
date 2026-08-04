<?php

namespace App\Exports;

use App\Exports\Concerns\StyledExportSheet;
use App\Models\PressTour;
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
 * The press / blogger / info-tour registry export — the sheet handed upward
 * once the tours have run. Columns follow the buyruq's own table, with the
 * plan-versus-fact part (state, actual date, filed documents) appended.
 */
class PressToursExport implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;
    use StyledExportSheet;

    private int $rowNumber = 0;

    /** @param  Builder<PressTour>  $query */
    public function __construct(private readonly Builder $query) {}

    /** @return Builder<PressTour> */
    public function query(): Builder
    {
        return (clone $this->query)->with('order')->withCount('attachments');
    }

    public function title(): string
    {
        return __('app.export.press_tours_sheet');
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            '№',
            __('app.label.press_tour_direction'),
            __('app.label.press_tour_name'),
            __('app.label.press_tour_place'),
            __('app.label.press_tour_period'),
            __('app.label.press_tour_people'),
            __('app.label.responsible'),
            __('app.label.press_tour_curator'),
            __('app.label.press_tour_foreign_partner'),
            __('app.label.order_basis'),
            __('app.label.press_tour_state'),
            __('app.label.press_tour_held_on'),
            __('app.label.press_tour_documents'),
            __('app.label.press_tour_notes'),
        ];
    }

    /**
     * @param  PressTour  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            ++$this->rowNumber,
            $row->direction?->label(),
            $row->name,
            $row->place,
            $row->period,
            // Exactly what the registry said — «6+11» is not a number.
            $row->peopleLabel(),
            $row->responsible,
            $row->curator,
            $row->foreign_partner,
            $row->order?->number,
            $row->state?->label(),
            $row->held_on?->format('d.m.Y'),
            $row->attachments_count,
            $row->notes,
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
                __('app.export.press_tours_title', ['year' => now()->year]),
            ),
        ];
    }
}
