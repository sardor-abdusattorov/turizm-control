<?php

namespace Database\Seeders;

use App\Models\Sponsor;
use Illuminate\Database\Seeder;

/**
 * Real sponsors from the exhibition registries: the carriers and venues that
 * fund the national stands (Uzbekistan Airways rows in the registries are
 * sponsor contributions). Contact data researched from official sites.
 */
class SponsorsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->sponsors() as $data) {
            Sponsor::query()->firstOrCreate(
                ['name' => $data['name']],
                array_merge($data, ['status' => true]),
            );
        }
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function sponsors(): array
    {
        return [
            [
                'name' => 'Uzbekistan Airways',
                'website' => 'https://www.uzairways.com',
                'phone' => '+998 78 140 02 00',
                'address' => 'г. Ташкент, пр-т Амира Темура, 41',
                'description' => 'Национальный авиаперевозчик Узбекистана. Генеральный спонсор национальных стендов на международных туристических выставках.',
            ],
            [
                'name' => 'Qanot Sharq',
                'website' => 'https://qanotsharq.com',
                'phone' => '+998 71 227 93 07',
                'address' => 'г. Ташкент',
                'description' => 'Частная авиакомпания Узбекистана (Airbus A320/A321). Участник национального стенда на ITB Berlin.',
            ],
            [
                'name' => 'Centrum Air',
                'website' => 'https://centrum-air.com',
                'phone' => null,
                'address' => 'г. Ташкент',
                'description' => 'Авиакомпания группы My Freighter (myfreighter.uz), выполняет пассажирские рейсы под брендом Centrum Air.',
            ],
            [
                'name' => 'Silk Road Samarkand',
                'website' => 'https://silkroad-samarkand.com',
                'phone' => '+998 55 705 55 55',
                'email' => 'info@silkroad-samarkand.com',
                'address' => 'г. Самарканд, ул. Конигиль, 4',
                'description' => 'Международный туристический центр в Самарканде: отели, конгресс-холл и «Вечный город». Спонсор стенда на IMEX-2026.',
            ],
        ];
    }
}
