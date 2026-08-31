<?php

namespace Database\Seeders;

use App\Enums\ProjectType;
use App\Models\Currency;
use App\Models\Project;
use Illuminate\Database\Seeder;

class Exhibitions2025Seeder extends Seeder
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
                    'area_is_free' => $data['area_is_free'],
                    'area_currency_id' => $data['area_currency'] ? $currencyIds[$data['area_currency']] ?? null : null,
                    'stand_cost' => $data['stand_cost'],
                    'stand_currency_id' => $currencyIds[$data['stand_currency']] ?? null,
                    'status' => true,
                ],
            );
        }
    }

    /** @return list<array<string, mixed>> */
    private function exhibitions(): array
    {
        return [
            ['name' => 'FITUR-2025', 'venue' => 'Madrid, Spain', 'starts_on' => '2025-01-22', 'ends_on' => '2025-01-26', 'area_sqm' => 76.5, 'area_cost' => 16595.41, 'area_is_free' => false, 'area_currency' => 'EUR', 'stand_cost' => 47890.0, 'stand_currency' => 'EUR'],
            ['name' => 'ITB BERLIN-2025', 'venue' => 'Berlin, Germany', 'starts_on' => '2025-03-04', 'ends_on' => '2025-03-06', 'area_sqm' => 162.0, 'area_cost' => 55000.0, 'area_is_free' => false, 'area_currency' => 'EUR', 'stand_cost' => 123000.0, 'stand_currency' => 'USD'],
            ['name' => 'SATTE-2025', 'venue' => 'New Delhi, India', 'starts_on' => '2025-02-19', 'ends_on' => '2025-02-21', 'area_sqm' => 54.0, 'area_cost' => 34381.19, 'area_is_free' => false, 'area_currency' => 'USD', 'stand_cost' => 29074.0, 'stand_currency' => 'USD'],
            ['name' => 'MITT-2025', 'venue' => 'Moscow, Russia', 'starts_on' => '2025-03-18', 'ends_on' => '2025-03-20', 'area_sqm' => 100.5, 'area_cost' => 86720.32, 'area_is_free' => false, 'area_currency' => 'USD', 'stand_cost' => 75000.0, 'stand_currency' => 'USD'],
            ['name' => 'ATM 25', 'venue' => 'Dubai, UAE', 'starts_on' => '2025-04-28', 'ends_on' => '2025-05-01', 'area_sqm' => 66.0, 'area_cost' => 84000.0, 'area_is_free' => false, 'area_currency' => 'USD', 'stand_cost' => 52705.8, 'stand_currency' => 'USD'],
            ['name' => 'SCITE Sichuan 2025', 'venue' => 'Chengdu, China', 'starts_on' => '2025-04-25', 'ends_on' => '2025-04-27', 'area_sqm' => 60.0, 'area_cost' => null, 'area_is_free' => true, 'area_currency' => null, 'stand_cost' => 25000.0, 'stand_currency' => 'USD'],
            ['name' => 'GITF Guanchjou 2025', 'venue' => 'Guangzhou, China', 'starts_on' => '2025-05-15', 'ends_on' => '2025-05-17', 'area_sqm' => 70.0, 'area_cost' => 34210.72, 'area_is_free' => false, 'area_currency' => 'USD', 'stand_cost' => 18368.0, 'stand_currency' => 'USD'],
            ['name' => 'SITF 2025', 'venue' => 'Seoul, South Korea', 'starts_on' => '2025-06-05', 'ends_on' => '2025-06-08', 'area_sqm' => 72.0, 'area_cost' => 25400.0, 'area_is_free' => false, 'area_currency' => 'USD', 'stand_cost' => 50950.0, 'stand_currency' => 'USD'],
            ['name' => 'ITE Hong Kong 2025', 'venue' => 'Hong Kong', 'starts_on' => '2025-06-12', 'ends_on' => '2025-06-15', 'area_sqm' => 70.0, 'area_cost' => 34210.0, 'area_is_free' => false, 'area_currency' => 'USD', 'stand_cost' => 18368.0, 'stand_currency' => 'USD'],
            ['name' => 'TT Warsaw 2025', 'venue' => 'Warsaw, Poland', 'starts_on' => '2025-10-10', 'ends_on' => '2025-10-12', 'area_sqm' => 105.0, 'area_cost' => 26850.0, 'area_is_free' => false, 'area_currency' => 'EUR', 'stand_cost' => 64000.0, 'stand_currency' => 'EUR'],
            ['name' => 'Matta Fair 2025', 'venue' => 'Kuala Lumpur, Malaysia', 'starts_on' => '2025-09-05', 'ends_on' => '2025-09-07', 'area_sqm' => 72.0, 'area_cost' => 7478.66, 'area_is_free' => false, 'area_currency' => 'USD', 'stand_cost' => 37900.0, 'stand_currency' => 'USD'],
            ['name' => 'TTG Rimini 2025', 'venue' => 'Rimini, Italy', 'starts_on' => '2025-10-08', 'ends_on' => '2025-10-10', 'area_sqm' => 96.0, 'area_cost' => 51141.0, 'area_is_free' => false, 'area_currency' => 'EUR', 'stand_cost' => 100000.0, 'stand_currency' => 'USD'],
            ['name' => 'IFTM Top Resa 2025', 'venue' => 'Paris, France', 'starts_on' => '2025-09-23', 'ends_on' => '2025-09-25', 'area_sqm' => 115.0, 'area_cost' => 88434.0, 'area_is_free' => false, 'area_currency' => 'EUR', 'stand_cost' => 90000.0, 'stand_currency' => 'EUR'],
            ['name' => 'Tourism Expo Japan 2025', 'venue' => 'Aichi, Japan', 'starts_on' => '2025-09-26', 'ends_on' => '2025-09-29', 'area_sqm' => 72.0, 'area_cost' => 27727.55, 'area_is_free' => false, 'area_currency' => 'USD', 'stand_cost' => 110000.0, 'stand_currency' => 'USD'],
            ['name' => 'World Travel Market-2025', 'venue' => 'London, UK', 'starts_on' => '2025-11-04', 'ends_on' => '2025-11-06', 'area_sqm' => 119.0, 'area_cost' => 90490.0, 'area_is_free' => false, 'area_currency' => 'GBP', 'stand_cost' => 94800.0, 'stand_currency' => 'GBP'],
            ['name' => 'QTM-2025', 'venue' => 'Doha, Qatar', 'starts_on' => '2025-11-24', 'ends_on' => '2025-11-26', 'area_sqm' => 36.0, 'area_cost' => 25000.0, 'area_is_free' => false, 'area_currency' => 'USD', 'stand_cost' => 60000.0, 'stand_currency' => 'USD'],
        ];
    }
}
