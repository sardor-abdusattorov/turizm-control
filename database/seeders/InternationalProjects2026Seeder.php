<?php

namespace Database\Seeders;

use App\Enums\ProjectType;
use App\Models\Currency;
use App\Models\Project;
use Database\Seeders\Concerns\LinksProjectParticipants;
use Illuminate\Database\Seeder;

/**
 * Import of the «Реестр Международных выставок 2026 года» registry
 * (14 exhibitions, incl. venues). Idempotent by (type, name). Airways rows
 * are sponsor contributions; participants link to the Contacts directory
 * where the registry spelling matches. NOTE: the source file's BITF-2026
 * «Итог» cell says 32 000 000 but its rows sum to 40 000 000 — the rows win.
 */
class InternationalProjects2026Seeder extends Seeder
{
    use LinksProjectParticipants;

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

            if (! $project->wasRecentlyCreated) {
                continue;
            }

            $project->participants()->createMany(collect($data['participants'])
                ->map(fn (array $row, int $index): array => [
                    'name' => $row[0],
                    'amount' => $row[1],
                    'currency_id' => $uzsId,
                    'sort' => $index,
                    'role' => $this->participantRole($row[0]),
                    'sponsor_id' => $this->sponsorIdFor($row[0]),
                    'contact_id' => $this->contactIdFor($row[0]),
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
                'name' => 'FITUR-2026 (Feria Internacional de Turismo)',
                'venue' => 'Madrid, Spain',
                'starts_on' => '2026-01-21',
                'ends_on' => '2026-01-25',
                'area_sqm' => 150.0,
                'area_cost' => 33921.0,
                'area_currency' => 'EUR',
                'stand_cost' => 1620000000.0,
                'stand_currency' => 'UZS',
                'participants' => [
                    ['OOO "Samarkand Touristic Centre"', 40000000],
                    ['OOO "TRAVEL ISTAN"', 40000000],
                    ['Samarcanda Adventures', 40000000],
                    ['OOO "Premium Trans"/Orient Star', 40000000],
                    ['OOO "ARCADA TRAVEL"', 40000000],
                    ['OOO "El MUNDO TOUR"', 40000000],
                    ['OOO "AVRUD"', 40000000],
                    ['Zamin DMC', 40000000],
                    ['Global EXPLORE/Uzbekistan Discovery', 40000000],
                    ['"Uzbekistan Airways"', 100000000],
                ],
            ],
            [
                'name' => '«Ferien Messe 2026»',
                'venue' => 'Vienna, Austria',
                'starts_on' => '2026-01-15',
                'ends_on' => '2026-01-18',
                'area_sqm' => 60.0,
                'area_cost' => 9880.83,
                'area_currency' => 'EUR',
                'stand_cost' => 60000.0,
                'stand_currency' => 'EUR',
                'participants' => [
                    ['"SULTAN TRAVEL UZBEKISTAN"', 10000000],
                    ['"East Asia Point"', 10000000],
                    ['OOO "Premium Trans"/Orient Star', 10000000],
                ],
            ],
            [
                'name' => 'Travel and Adventure Show',
                'venue' => 'United States',
                'starts_on' => '2026-01-25',
                'ends_on' => '2026-01-26',
                'area_sqm' => 55.7,
                'area_cost' => 26970.0,
                'area_currency' => 'USD',
                'stand_cost' => 139877.28,
                'stand_currency' => 'USD',
                'participants' => [
                    ['Zumrad travel', 0],
                    ['Uzairways', 0],
                    ['Tashkent travel hub', 0],
                    ['Sogda tour', 0],
                    ['Milliy tourism group', 0],
                    ['Travel together', 0],
                    ['Silk Road treasures', 0],
                    ['Akbar travel', 0],
                ],
            ],
            [
                'name' => '"BIT 2026" (Borsa Internazionale del Turismo)',
                'venue' => 'Milan, Italy',
                'starts_on' => '2026-02-10',
                'ends_on' => '2026-02-12',
                'area_sqm' => 64.0,
                'area_cost' => 20005.0,
                'area_currency' => 'EUR',
                'stand_cost' => 4637000.0,
                'stand_currency' => 'RUB',
                'participants' => [
                    ['OOO "FINANCE WORLD SERVICES"', 15000000],
                    ['OOO AFSONA TRAVEL', 15000000],
                    ['Zumrad Travel', 7500000],
                    ['Anur Tour', 15000000],
                ],
            ],
            [
                'name' => '"Danish Travel show 2026"',
                'venue' => 'Herning, Denmark',
                'starts_on' => '2026-02-20',
                'ends_on' => '2026-02-22',
                'area_sqm' => 48.0,
                'area_cost' => 8423.73,
                'area_currency' => 'EUR',
                'stand_cost' => 63500.0,
                'stand_currency' => 'EUR',
                'participants' => [
                    ['OOO "Premium Trans"/Orient Star', 10000000],
                    ['"Miramax Travel Management"', 10000000],
                    ['"Tashkent Travel Hub"', 10000000],
                ],
            ],
            [
                'name' => '"B-Travel 2026"',
                'venue' => 'Barcelona, Spain',
                'starts_on' => '2026-03-20',
                'ends_on' => '2026-03-22',
                'area_sqm' => 94.0,
                'area_cost' => 25941.0,
                'area_currency' => 'EUR',
                'stand_cost' => 64775.0,
                'stand_currency' => 'EUR',
                'participants' => [
                    ['OOO "Global Explore"', 10000000],
                    ['OOO "El Mundo Tour"', 10000000],
                    ['OOO "Samarkand Touristic Centre"', 10000000],
                    ['OOO "Premium Trans"/Orient Star', 10000000],
                    ['OOO "Zamin DMC"', 10000000],
                    ['OOO "Smart Tours"/Travellers', 10000000],
                    ['"Uzbekistan Airways"', 100000000],
                ],
            ],
            [
                'name' => '"SATTE-2026" (South Asia\'s Travel & Tourism Exchange)',
                'venue' => 'New Delhi, India',
                'starts_on' => '2026-02-25',
                'ends_on' => '2026-02-27',
                'area_sqm' => 120.0,
                'area_cost' => 81604.08,
                'area_currency' => 'USD',
                'stand_cost' => 96375.51,
                'stand_currency' => 'USD',
                'participants' => [
                    ['"Uzbekistan Airways"', 70000000],
                    ['"Akfa Dream World"/Hilton', 15000000],
                    ['OOO "My Frieghter"/Centrum Air', 15000000],
                    ['OOO "Kazin DMC"', 15000000],
                    ['OOO "BEYOND DMC"', 15000000],
                    ['OOO "ENJOY TRAVEL"', 15000000],
                    ['OK "EAST ASIA POINT"', 15000000],
                    ['Rezbook global', 15000000],
                ],
            ],
            [
                'name' => '"OTM Mumbai 2026" (Outbound Travel Mart 2026)',
                'venue' => 'Mumbai, India',
                'starts_on' => '2026-02-05',
                'ends_on' => '2026-02-07',
                'area_sqm' => 72.0,
                'area_cost' => 47152.8,
                'area_currency' => 'USD',
                'stand_cost' => 57324.4,
                'stand_currency' => 'USD',
                'participants' => [
                    ['"Enjoy Travel"', 10000000],
                    ['OK "ONS Travels"', 10000000],
                    ['OOO "Akfa Dream World"/Hilton', 10000000],
                    ['OOO "FAYZ GRAND MINION"/Bazar Travel', 10000000],
                    ['Akbar Travel', 10000000],
                ],
            ],
            [
                'name' => '"ITB-2026" Берлин',
                'venue' => 'Berlin, German',
                'starts_on' => '2026-03-03',
                'ends_on' => '2026-03-05',
                'area_sqm' => 202.0,
                'area_cost' => 59000.0,
                'area_currency' => 'EUR',
                'stand_cost' => 119900.0,
                'stand_currency' => 'EUR',
                'participants' => [
                    ['"International Caravan Travel Service Co.,Ltd"', 40000000],
                    ['"CREATIVE TOURS" MCHJ', 40000000],
                    ['"GEO TOUR SERVICE"', 40000000],
                    ['"ANTIQUE TRAVEL EXPERTS" OK', 40000000],
                    ['"TRAVEL FACTORY" MCHJ', 40000000],
                    ['"SITARA INTERNATIONAL LTD" MCHJ', 40000000],
                    ['"SEZAM TRAVEL" MCHJ', 40000000],
                    ['"PEOPLETRAVEL" MCHJ', 40000000],
                    ['"AKFA DREAM WORLD" MCHJ', 40000000],
                    ['"DARVESH TUR SERVS" MCHJ', 40000000],
                    ['OOO "Premium Trans"/Orient Star', 40000000],
                    ['OOO "GLOBAL EXPLORE"/Uzbekistan Discovery', 40000000],
                    ['OOO "MEGA TOUR"', 40000000],
                    ['OOO "QANOT SHARQ"', 40000000],
                    ['OOO "ZAMIN DMC"', 40000000],
                    ['OOO "IMRAN TOURS"', 40000000],
                ],
            ],
            [
                'name' => '"MITT 2026"',
                'venue' => 'Москва, Россия',
                'starts_on' => '2026-03-11',
                'ends_on' => '2026-03-13',
                'area_sqm' => 135.0,
                'area_cost' => 116713.0,
                'area_currency' => 'USD',
                'stand_cost' => 8235542.0,
                'stand_currency' => 'RUB',
                'participants' => [
                    ['OOO "Dolores Travel Services"', 30000000],
                    ['OOO "CENTRAL ASIA TRAVEL"', 30000000],
                    ['OOO "UKTAMXON TOUR"', 30000000],
                    ['OOO "Samarkand Touristic Centre"', 30000000],
                    ['"Uzbekistan Airways"', 120000000],
                    ['OOO "Premium Trans"/Orient Star', 30000000],
                    ['Arda Khiva', 0],
                    ['Inter MICE Asia', 30000000],
                    ['TIMURWAY', 30000000],
                ],
            ],
            [
                'name' => '"ITB China - 2026"',
                'venue' => 'Shanghai, China',
                'starts_on' => '2026-05-26',
                'ends_on' => '2026-05-28',
                'area_sqm' => 81.0,
                'area_cost' => 23000.0,
                'area_currency' => 'USD',
                'stand_cost' => 56127.0,
                'stand_currency' => 'USD',
                'participants' => [
                    ['OOO "Asia trip"', 20000000],
                    ['OOO "IMRAN TOURS"', 20000000],
                    ['"BEYOND EXPECTATIONS"', 20000000],
                    ['OOO "Afsona Travel"', 20000000],
                    ['OOO "Smart Tours"', 20000000],
                    ['OOO "Premium Trans"/Orient Star', 20000000],
                    ['OOO "Samarkand Touristic Centre"', 20000000],
                    ['"UZTURINVESTMENT AND DEVELOPMENT"', 20000000],
                ],
            ],
            [
                'name' => '"KITF-2026"',
                'venue' => 'Алматы, Казахстан',
                'starts_on' => '2026-04-22',
                'ends_on' => '2026-04-24',
                'area_sqm' => 56.0,
                'area_cost' => 21806.74,
                'area_currency' => 'USD',
                'stand_cost' => 40350.0,
                'stand_currency' => 'USD',
                'participants' => [
                    ['OOO "Samarkand Touristic Centre"', 15000000],
                    ['"OKS TOURS"', 15000000],
                    ['Uzairways', 120000000],
                    ['Hotel Pro/Arda Xiva', 0],
                ],
            ],
            [
                'name' => 'BITF-2026',
                'venue' => 'Бишкек, Кыргызстан',
                'starts_on' => '2026-04-30',
                'ends_on' => '2026-05-02',
                'area_sqm' => 48.0,
                'area_cost' => 9108.6,
                'area_currency' => 'USD',
                'stand_cost' => 24300.0,
                'stand_currency' => 'USD',
                'participants' => [
                    ['Zilol Sanatoriya', 8000000],
                    ['Tour Link/Premium Trans', 8000000],
                    ['Cheksiz Trip', 8000000],
                    ['Sherobod Turizm', 8000000],
                    ['TIC Travel', 8000000],
                ],
            ],
            [
                'name' => 'IMEX - 2026',
                'venue' => 'Frankfurt, Германия',
                'starts_on' => '2026-05-19',
                'ends_on' => '2026-05-21',
                'area_sqm' => 30.0,
                'area_cost' => 24115.0,
                'area_currency' => 'EUR',
                'stand_cost' => 38153.0,
                'stand_currency' => 'EUR',
                'participants' => [
                    ['Silk Road Samarkand', 60000000],
                    ['OOO "ZAMIN DMC"/Asia Luxe', 40000000],
                    ['Tour Link/Premium Trans', 40000000],
                    ['El Mundo', 40000000],
                ],
            ],
        ];
    }
}
