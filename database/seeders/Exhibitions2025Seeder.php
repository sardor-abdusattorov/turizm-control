<?php

namespace Database\Seeders;

use App\Enums\ProjectType;
use App\Models\Currency;
use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * One-off import of the "Реестр Международных выставок 2025 года" registry
 * (16 international exhibitions with their participant fees). Idempotent:
 * a project that already exists by (type, name) is left untouched, so
 * re-seeding never duplicates participants.
 *
 * The registry's "profit" column is intentionally NOT imported: it always
 * equals the sum of participant fees (verified for all 16 rows), so the app
 * computes it via Project::feesTotal().
 */
class Exhibitions2025Seeder extends Seeder
{
    public function run(): void
    {
        $currencyIds = Currency::query()->pluck('id', 'short_name');
        $uzsId = $currencyIds['UZS'] ?? null;

        foreach ($this->exhibitions() as $data) {
            $project = Project::firstOrCreate(
                [
                    'type' => ProjectType::International->value,
                    'name' => $data['name'],
                ],
                [
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

            if (! $project->wasRecentlyCreated) {
                continue;
            }

            $project->participants()->createMany(collect($data['participants'])
                ->map(fn (array $row, int $index): array => [
                    'name' => $row[0],
                    'amount' => $row[1],
                    'currency_id' => $uzsId,
                    'sort' => $index,
                ])
                ->all());
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exhibitions(): array
    {
        return [
            [
                'name' => 'FITUR-2025',
                'starts_on' => '2025-01-22',
                'ends_on' => '2025-01-26',
                'area_sqm' => 76.5,
                'area_cost' => 16595.41,
                'area_is_free' => false,
                'area_currency' => 'EUR',
                'stand_cost' => 47890.0,
                'stand_currency' => 'EUR',
                'participants' => [
                    ['ROCKET DMC REGISTAN MCHJ', 38000000],
                    ['ZAMIN DMC MCHJ', 38000000],
                    ['ООО "SAMARCANDA ADVENTURES"', 38000000],
                    ['"ZAMIN-TRAVEL" MCHJ', 38000000],
                    ['"DOLORES TRAVEL SERVICES " MCHJ', 38000000],
                    ['"GLOBAL EXPLORE" MCHJ', 38000000],
                    ['"SOUNDER VOYAGE" MCHJ', 38000000],
                    ['"ADVANTOUR" MCHJ', 38000000],
                    ['"OLIMPIK TURSERVIS" MCHJ', 38000000],
                    ['"EL MUNDO TOUR" MCHJ', 38000000],
                    ['ORIENT STAR GROUP MCHJ', 38000000],
                ],
            ],
            [
                'name' => 'ITB BERLIN-2025',
                'starts_on' => '2025-03-04',
                'ends_on' => '2025-03-06',
                'area_sqm' => 162.0,
                'area_cost' => 55000.0,
                'area_is_free' => false,
                'area_currency' => 'EUR',
                'stand_cost' => 123000.0,
                'stand_currency' => 'USD',
                'participants' => [
                    ['CENTRAL ASIA TRAVEL MCHJ', 40000000],
                    ['SEZAM TRAVEL MCHJ', 40000000],
                    ['PEOPLETRAVEL MCHJ', 40000000],
                    ['ANTIQUI TRAVEL EXPERTS', 40000000],
                    ['DOLORES TRAVEL SERVICE MCHJ', 40000000],
                    ['MEGATOUR MCHJ', 40000000],
                    ['IMRAN-TOURS MCHJ', 40000000],
                    ['GEO TOUR SERVICE MCHJ', 40000000],
                    ['ORIENT STAR GROUP MCHJ', 40000000],
                    ['ROCKET DMC REGISTAN MCHJ', 120000000],
                    ['ZAMIN DMC MCHJ', 100000000],
                    ['SITARA INTERNATIONAL LTD', 40000000],
                    ['ANUR TOUR MCHJ', 40000000],
                    ['"OLIMPIK TURSERVIS" MCHJ', 40000000],
                    ['AJ UZBEKISTAN AIRWAYS', 198360000],
                ],
            ],
            [
                'name' => 'SATTE-2025',
                'starts_on' => '2025-02-19',
                'ends_on' => '2025-02-21',
                'area_sqm' => 54.0,
                'area_cost' => 34381.19,
                'area_is_free' => false,
                'area_currency' => 'USD',
                'stand_cost' => 29074.0,
                'stand_currency' => 'USD',
                'participants' => [
                    ['ZAMIN DMC MCHJ', 20000000],
                    ['MIRAN TRIPS MCHJ', 16000000],
                    ['GEO TOUR SERVICE MCHJ', 20000000],
                    ['ONS TRAVEL', 20000000],
                    ['ENJOY TRAVEL', 20000000],
                    ['AKFA DREAM WORLD MCHJ', 40000000],
                    ['REZBOOK GLOBAL INTERNATIONAL', 20000000],
                    ['ROCKET DMC REGISTAN MCHJ', 20000000],
                ],
            ],
            [
                'name' => 'MITT-2025',
                'starts_on' => '2025-03-18',
                'ends_on' => '2025-03-20',
                'area_sqm' => 100.5,
                'area_cost' => 86720.32,
                'area_is_free' => false,
                'area_currency' => 'USD',
                'stand_cost' => 75000.0,
                'stand_currency' => 'USD',
                'participants' => [
                    ['DOLORES TRAVEL SERVICE MCHJ', 35000000],
                    ['CENTRAL ASIA TRAVEL MCHJ', 35000000],
                    ['TIMURWAY TOUR MCHJ', 35000000],
                    ['SOLE VITO MCHJ', 35000000],
                    ['CATO MOTOROS', 35000000],
                    ['OOO "Selfie Travel"', 35000000],
                    ['СП "Beyond Expectations"', 35000000],
                    ['"Uzbekistan Airways"', 35000000],
                ],
            ],
            [
                'name' => 'ATM 25',
                'starts_on' => '2025-04-28',
                'ends_on' => '2025-05-01',
                'area_sqm' => 66.0,
                'area_cost' => 84000.0,
                'area_is_free' => false,
                'area_currency' => 'USD',
                'stand_cost' => 52705.8,
                'stand_currency' => 'USD',
                'participants' => [
                    ['OOO "BEYOND DMC"', 10000000],
                    ['OOO "ZAMIN DMC MCHJ"', 10000000],
                    ['OOO "Rezbook Global International"', 10000000],
                    ['СП "Beyond Expectations"', 10000000],
                    ['OOO "VERSAILLES TRAVEL"', 10000000],
                    ['СП "Sanat Travel Experts"', 10000000],
                    ['OOO "ASIA LUXE TRAVEL"', 10000000],
                    ['"Uzbekistan Airways"', 10000000],
                ],
            ],
            [
                'name' => 'SCITE Sichuan 2025',
                'starts_on' => '2025-04-25',
                'ends_on' => '2025-04-27',
                'area_sqm' => 60.0,
                'area_cost' => null,
                'area_is_free' => true,
                'area_currency' => null,
                'stand_cost' => 25000.0,
                'stand_currency' => 'USD',
                'participants' => [
                    ['OOO "SunRoad"', 5000000],
                    ['OOO "Right Flight"', 5000000],
                ],
            ],
            [
                'name' => 'GITF Guanchjou 2025',
                'starts_on' => '2025-05-15',
                'ends_on' => '2025-05-17',
                'area_sqm' => 70.0,
                'area_cost' => 34210.72,
                'area_is_free' => false,
                'area_currency' => 'USD',
                'stand_cost' => 18368.0,
                'stand_currency' => 'USD',
                'participants' => [
                    ['OOO "CAUCASSIA TRAVEL"', 5000000],
                    ['OOO "ZAMIN DMC MCHJ"', 5000000],
                    ['OOO"UZTUR INVESTMENT AND DEVELOPMENT"', 5000000],
                    ['OOO "Oriet Star Group"', 5000000],
                    ['OOO "SunRoad"', 5000000],
                    ['OOO " ANOTHER TRAVEL"', 5000000],
                ],
            ],
            [
                'name' => 'SITF 2025',
                'starts_on' => '2025-06-05',
                'ends_on' => '2025-06-08',
                'area_sqm' => 72.0,
                'area_cost' => 25400.0,
                'area_is_free' => false,
                'area_currency' => 'USD',
                'stand_cost' => 50950.0,
                'stand_currency' => 'USD',
                'participants' => [
                    ['OOO "INTURIZM"', 10000000],
                    ['"CENTRAL ASIA TRAVEL" OOO', 10000000],
                    ['EAST STAR HOTEL', 10000000],
                    ['OOO "Oriet Star Group"', 10000000],
                    ['ООО "My Freighter"', 0],
                    ['OOO "TRAVEL RENTCAR"', 0],
                    ['"Uzbekistan Airways"', 10000000],
                ],
            ],
            [
                'name' => 'ITE Hong Kong 2025',
                'starts_on' => '2025-06-12',
                'ends_on' => '2025-06-15',
                'area_sqm' => 70.0,
                'area_cost' => 34210.0,
                'area_is_free' => false,
                'area_currency' => 'USD',
                'stand_cost' => 18368.0,
                'stand_currency' => 'USD',
                'participants' => [
                    ['OOO "Selfie Travel"', 10000000],
                    ['OOO "Oriet Star Group"', 10000000],
                    ['OOO "ROCKET DMC REGISTAN"', 10000000],
                    ['OOO "IZEL"', 10000000],
                ],
            ],
            [
                'name' => 'TT Warsaw 2025',
                'starts_on' => '2025-10-10',
                'ends_on' => '2025-10-12',
                'area_sqm' => 105.0,
                'area_cost' => 26850.0,
                'area_is_free' => false,
                'area_currency' => 'EUR',
                'stand_cost' => 64000.0,
                'stand_currency' => 'EUR',
                'participants' => [
                    ['OOO "Oriet Star Group"', 10000000],
                    ['OOO "Geo Tour Service"', 10000000],
                    ['СП OOO "DOLORES TRAVEL SERVICES"', 10000000],
                    ['OOO "HOLIDAY TRAVEL"', 10000000],
                    ['OOO "Samarkanda Travel and Tours"', 10000000],
                    ['OOO "GLOBAL EXPLORE"', 10000000],
                    ['OOO "TOUR EAST"', 10000000],
                    ['OOO "AFSONA TRAVEL"', 10000000],
                ],
            ],
            [
                'name' => 'Matta Fair 2025',
                'starts_on' => '2025-09-05',
                'ends_on' => '2025-09-07',
                'area_sqm' => 72.0,
                'area_cost' => 7478.66,
                'area_is_free' => false,
                'area_currency' => 'USD',
                'stand_cost' => 37900.0,
                'stand_currency' => 'USD',
                'participants' => [
                    ['"ZIYARAH-TRAVEL" MCHJ', 10000000],
                    ['"ANTIQUE TRAVEL EXPERTS" OK', 10000000],
                    ['"BEYOND EXPECTATIONS" OK', 10000000],
                    ['"GEO TOUR SERVICE" MCHJ', 10000000],
                    ['СП Sanat Travel Experts"', 10000000],
                    ['OOO "BEYOND DMC"', 10000000],
                    ['OOO "SkyWay DMC"', 10000000],
                ],
            ],
            [
                'name' => 'TTG Rimini 2025',
                'starts_on' => '2025-10-08',
                'ends_on' => '2025-10-10',
                'area_sqm' => 96.0,
                'area_cost' => 51141.0,
                'area_is_free' => false,
                'area_currency' => 'EUR',
                'stand_cost' => 100000.0,
                'stand_currency' => 'USD',
                'participants' => [
                    ['"PEOPLETRAVEL"', 35000000],
                    ['OOO "Oriet Star Group"', 35000000],
                    ['OOO "CENTRAL ASIA TRAVEL"', 35000000],
                    ['СП ООО "Dolores Travel Service\'', 35000000],
                    ['OOO "GLOBAL EXPLORE"', 35000000],
                    ['OOO "Inter MICE Asia"', 35000000],
                    ['OOO "MEGATOUR"', 35000000],
                    ['OOO "Uktamxon Tour"', 35000000],
                    ['OOO "Sapphire Asia"', 35000000],
                    ['OOO "IMRAN-TOURS"', 35000000],
                    ['OOO "Samarkanda Adventures"', 35000000],
                    ['"Uzbekistan Airways"', 35000000],
                ],
            ],
            [
                'name' => 'IFTM Top Resa 2025',
                'starts_on' => '2025-09-23',
                'ends_on' => '2025-09-25',
                'area_sqm' => 115.0,
                'area_cost' => 88434.0,
                'area_is_free' => false,
                'area_currency' => 'EUR',
                'stand_cost' => 90000.0,
                'stand_currency' => 'EUR',
                'participants' => [
                    ['East Asia Point', 30000000],
                    ['OOO "ANUR TOUR"', 30000000],
                    ['OOO "KARAVAN TRAVEL"', 30000000],
                    ['OOO "MEGATOUR"', 30000000],
                    ['OOO "SACRED EAST TRAVEL"', 30000000],
                    ['OOO "Oriet Star Group"', 30000000],
                    ['OOO "Uktamxon Tour"', 30000000],
                    ['OOO "Selfie Travel"', 30000000],
                    ['OOO "CENTRAL ASIA TRAVEL"', 30000000],
                    ['ЧП "EMERALD TRAVEL"', 30000000],
                    ['UZBEKISTAN AIRWAYS AJ', 70000000],
                ],
            ],
            [
                'name' => 'Tourism Expo Japan 2025',
                'starts_on' => '2025-09-26',
                'ends_on' => '2025-09-29',
                'area_sqm' => 72.0,
                'area_cost' => 27727.55,
                'area_is_free' => false,
                'area_currency' => 'USD',
                'stand_cost' => 110000.0,
                'stand_currency' => 'USD',
                'participants' => [
                    ['OOO "Oriet Star Group"', 30000000],
                    ['OOO"ZAMIN DMC"', 0],
                    ['OOO "Moderntravel"', 30000000],
                    ['СП OOO "DOLORES TRAVEL SERVICES"', 30000000],
                    ['OOO Tashkent Travel Hub"', 30000000],
                    ['"Uzbekistan Airways', 30000000],
                ],
            ],
            [
                'name' => 'World Travel Market-2025',
                'starts_on' => '2025-11-04',
                'ends_on' => '2025-11-06',
                'area_sqm' => 119.0,
                'area_cost' => 90490.0,
                'area_is_free' => false,
                'area_currency' => 'GBP',
                'stand_cost' => 94800.0,
                'stand_currency' => 'GBP',
                'participants' => [
                    ['OOO "Dolores Travel Services"', 40000000],
                    ['OOO "Travel ISTAN"', 40000000],
                    ['СП "Sanat Travel Experts"', 40000000],
                    ['OOO "Oriet Star Group"', 40000000],
                    ['OOO "Ariana Tours"', 40000000],
                    ['OOO "GLOBAL EXPLORE"', 40000000],
                    ['OOO "Orient Voyages"', 40000000],
                    ['OOO "ZAMIN DMC', 40000000],
                    ['OOO "ANUR TOUR"', 40000000],
                    ['OOO "Akbar Travel Asia"', 40000000],
                    ['OOO "Sitara International" LTD', 40000000],
                    ['OOO "MY FREIGHTER"', 40000000],
                    ['UZBEKISTAN AIRWAYS AJ', 100000000],
                ],
            ],
            [
                'name' => 'QTM-2025',
                'starts_on' => '2025-11-24',
                'ends_on' => '2025-11-26',
                'area_sqm' => 36.0,
                'area_cost' => 25000.0,
                'area_is_free' => false,
                'area_currency' => 'USD',
                'stand_cost' => 60000.0,
                'stand_currency' => 'USD',
                'participants' => [
                    ['OOO "Zamin DMC"', 10000000],
                    ['"MY-TRIPGUIDE"', 0],
                    ['"BEYOND DMC"', 10000000],
                    ['"FAYZ GRAND MINION', 10000000],
                    ['OOO "SULTAN TRAVEL UZBEKISTAN"', 10000000],
                ],
            ],
        ];
    }
}
