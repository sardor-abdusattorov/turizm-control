<?php

namespace App\Exports;

use App\Exports\Concerns\StyledExportSheet;
use App\Models\Sponsor;
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

class SponsorsExport implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;
    use StyledExportSheet;

    private int $rowNumber = 0;

    /** @param  Builder<Sponsor>  $query */
    public function __construct(private readonly Builder $query) {}

    /** @return Builder<Sponsor> */
    public function query(): Builder
    {
        return (clone $this->query)
            ->withCount('participations')
            ->reorder('name');
    }

    public function title(): string
    {
        return __('app.label.sponsors');
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            '№',
            __('app.label.name'),
            __('app.label.inn'),
            __('app.label.contact_person'),
            __('app.label.phone'),
            __('app.label.email'),
            __('app.label.website'),
            __('app.label.address'),
            __('app.label.projects'),
            __('app.label.status'),
            __('app.label.created_at'),
        ];
    }

    /** @return array<int, mixed> */
    public function map($row): array
    {
        return [
            ++$this->rowNumber,
            $row->name,
            $row->inn,
            $row->contact_person,
            $row->phone,
            $row->email,
            $row->website,
            $row->address,
            (int) ($row->participations_count ?? 0),
            $row->status ? __('app.label.active') : __('app.label.inactive'),
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
                __('app.export.sponsors_title', ['year' => now()->year]),
                ['C', 'E'], // INN, phone stay text
            ),
        ];
    }
}
