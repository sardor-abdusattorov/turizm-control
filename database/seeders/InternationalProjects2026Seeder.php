<?php

namespace Database\Seeders;

use App\Enums\ProjectType;
use App\Models\Currency;
use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * Import of the «Реестр Международных выставок 2026 года» registry (14
 * exhibitions, incl. venues). Only the project shells are seeded — venue,
 * dates, площадь and стенд costs; participation income is entered by hand as
 * income contracts against each project. Idempotent by (type, name).
 */
class InternationalProjects2026Seeder extends Seeder
{
    public function run(): void
    {
        $currencyIds = Currency::query()->pluck('id', 'short_name');

        foreach ($this->exhibitions() as $data) {
            Project::firstOrCreate(
                [
                    'type' => ProjectType::International->value,
                    'name' => $data['name'],
                ],
                [
                    'venue' => $data['venue'],
                    'starts_on' => $data['starts_on'],
                    'ends_on' => $data['ends_on'],
                    'area_sqm' => $data['area_sqm'],
                    'area_cost' => $data['area_cost'],
                    'area_is_free' => false,
                    'area_currency_id' => $currencyIds[$data['area_currency']] ?? null,
                    'stand_cost' => $data['stand_cost'],
                    'stand_currency_id' => $currencyIds[$data['stand_currency']] ?? null,
                    'status' => true,
                ],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exhibitions(): array
    {
        return [
            ['name' => 'FITUR-2026 (Feria Internacional de Turismo)', 'venue' => 'Madrid, Spain', 'starts_on' => '2026-01-21', 'ends_on' => '2026-01-25', 'area_sqm' => 150.0, 'area_cost' => 33921.0, 'area_currency' => 'EUR', 'stand_cost' => 1620000000.0, 'stand_currency' => 'UZS'],
            ['name' => '«Ferien Messe 2026»', 'venue' => 'Vienna, Austria', 'starts_on' => '2026-01-15', 'ends_on' => '2026-01-18', 'area_sqm' => 60.0, 'area_cost' => 9880.83, 'area_currency' => 'EUR', 'stand_cost' => 60000.0, 'stand_currency' => 'EUR'],
            ['name' => 'Travel and Adventure Show', 'venue' => 'United States', 'starts_on' => '2026-01-25', 'ends_on' => '2026-01-26', 'area_sqm' => 55.7, 'area_cost' => 26970.0, 'area_currency' => 'USD', 'stand_cost' => 139877.28, 'stand_currency' => 'USD'],
            ['name' => '"BIT 2026" (Borsa Internazionale del Turismo)', 'venue' => 'Milan, Italy', 'starts_on' => '2026-02-10', 'ends_on' => '2026-02-12', 'area_sqm' => 64.0, 'area_cost' => 20005.0, 'area_currency' => 'EUR', 'stand_cost' => 4637000.0, 'stand_currency' => 'RUB'],
            ['name' => '"Danish Travel show 2026"', 'venue' => 'Herning, Denmark', 'starts_on' => '2026-02-20', 'ends_on' => '2026-02-22', 'area_sqm' => 48.0, 'area_cost' => 8423.73, 'area_currency' => 'EUR', 'stand_cost' => 63500.0, 'stand_currency' => 'EUR'],
            ['name' => '"B-Travel 2026"', 'venue' => 'Barcelona, Spain', 'starts_on' => '2026-03-20', 'ends_on' => '2026-03-22', 'area_sqm' => 94.0, 'area_cost' => 25941.0, 'area_currency' => 'EUR', 'stand_cost' => 64775.0, 'stand_currency' => 'EUR'],
            ['name' => '"SATTE-2026" (South Asia\'s Travel & Tourism Exchange)', 'venue' => 'New Delhi, India', 'starts_on' => '2026-02-25', 'ends_on' => '2026-02-27', 'area_sqm' => 120.0, 'area_cost' => 81604.08, 'area_currency' => 'USD', 'stand_cost' => 96375.51, 'stand_currency' => 'USD'],
            ['name' => '"OTM Mumbai 2026" (Outbound Travel Mart 2026)', 'venue' => 'Mumbai, India', 'starts_on' => '2026-02-05', 'ends_on' => '2026-02-07', 'area_sqm' => 72.0, 'area_cost' => 47152.8, 'area_currency' => 'USD', 'stand_cost' => 57324.4, 'stand_currency' => 'USD'],
            ['name' => '"ITB-2026" Берлин', 'venue' => 'Berlin, German', 'starts_on' => '2026-03-03', 'ends_on' => '2026-03-05', 'area_sqm' => 202.0, 'area_cost' => 59000.0, 'area_currency' => 'EUR', 'stand_cost' => 119900.0, 'stand_currency' => 'EUR'],
            ['name' => '"MITT 2026"', 'venue' => 'Москва, Россия', 'starts_on' => '2026-03-11', 'ends_on' => '2026-03-13', 'area_sqm' => 135.0, 'area_cost' => 116713.0, 'area_currency' => 'USD', 'stand_cost' => 8235542.0, 'stand_currency' => 'RUB'],
            ['name' => '"ITB China - 2026"', 'venue' => 'Shanghai, China', 'starts_on' => '2026-05-26', 'ends_on' => '2026-05-28', 'area_sqm' => 81.0, 'area_cost' => 23000.0, 'area_currency' => 'USD', 'stand_cost' => 56127.0, 'stand_currency' => 'USD'],
            ['name' => '"KITF-2026"', 'venue' => 'Алматы, Казахстан', 'starts_on' => '2026-04-22', 'ends_on' => '2026-04-24', 'area_sqm' => 56.0, 'area_cost' => 21806.74, 'area_currency' => 'USD', 'stand_cost' => 40350.0, 'stand_currency' => 'USD'],
            ['name' => 'BITF-2026', 'venue' => 'Бишкек, Кыргызстан', 'starts_on' => '2026-04-30', 'ends_on' => '2026-05-02', 'area_sqm' => 48.0, 'area_cost' => 9108.6, 'area_currency' => 'USD', 'stand_cost' => 24300.0, 'stand_currency' => 'USD'],
            ['name' => 'IMEX - 2026', 'venue' => 'Frankfurt, Германия', 'starts_on' => '2026-05-19', 'ends_on' => '2026-05-21', 'area_sqm' => 30.0, 'area_cost' => 24115.0, 'area_currency' => 'EUR', 'stand_cost' => 38153.0, 'stand_currency' => 'EUR'],
        ];
    }
}
