<?php

namespace Database\Seeders;

use App\Enums\OrderScope;
use App\Models\Order;
use App\Models\PressTour;
use App\Models\User;
use Illuminate\Database\Seeder;

class PressTours2026Seeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->tours() as $data) {
            PressTour::firstOrCreate(
                [
                    'direction' => $data['direction'],
                    'name' => $data['name'],
                    'period' => $data['period'],
                ],
                [
                    'place' => $data['place'],
                    'starts_month' => $data['starts_month'],
                    'people_count' => $data['people_count'],
                    'people_note' => $data['people_note'],
                    'responsible' => $data['responsible'],
                    'curator' => $data['curator'],
                    'foreign_partner' => $data['foreign_partner'],
                    'order_id' => $data['order'] ? $this->orderId($data['order']) : null,
                    'notes' => $data['notes'],
                    'status' => true,
                ],
            );
        }
    }

    private function orderId(string $number): ?int
    {
        $creator = User::query()->firstWhere('email', 'manager@test.uz')
            ?? User::query()->orderBy('id')->first();

        if (! $creator) {
            return null;
        }

        return Order::firstOrCreate(
            ['number' => $number],
            [
                'scope' => OrderScope::PrCenter->value,
                'title' => 'Об организации пресс-туров и информационных туров в 2026 году',
                'issued_at' => '2026-01-01',
                'status' => true,
                'created_by' => $creator->id,
            ],
        )->id;
    }

    /** @return list<array<string, mixed>> */
    private function tours(): array
    {
        return [

            ['direction' => 'inbound', 'name' => 'Визит СМИ и блогеров Азербайджана в Узбекистан', 'place' => 'Азербайджан', 'period' => 'август', 'starts_month' => 8, 'people_count' => null, 'people_note' => null, 'responsible' => 'Национальный ПР центр', 'curator' => 'Хаёт Хамраев', 'foreign_partner' => null, 'order' => null, 'notes' => 'Из таблицы проектов. Тур в Ташкент-Самарканд, Бухара-Хива'],
            ['direction' => 'inbound', 'name' => 'Компания Travco Holidays организует пресс-тур для журналистов и блогеров', 'place' => 'Египет', 'period' => 'июль-август', 'starts_month' => 7, 'people_count' => 10, 'people_note' => null, 'responsible' => 'Шерзод Султонов', 'curator' => 'Хаёт Хамраев', 'foreign_partner' => 'Посольство Узбекистана в Египте', 'order' => null, 'notes' => 'Инфа от маркетинга'],
            ['direction' => 'inbound', 'name' => 'Визит Грузинский СМИ и турагенты на Узбекской и Грузинский туристический форум', 'place' => 'Грузия', 'period' => '11-18 Август', 'starts_month' => 8, 'people_count' => null, 'people_note' => '6+11', 'responsible' => 'Умида Талипова', 'curator' => 'Хаёт Хамраев', 'foreign_partner' => null, 'order' => null, 'notes' => 'Инфа от маркетинга'],
            ['direction' => 'inbound', 'name' => 'Визит сёмычный группы с Алжира', 'place' => 'Алжир', 'period' => 'Август', 'starts_month' => 8, 'people_count' => null, 'people_note' => null, 'responsible' => 'Шерзод Султонов', 'curator' => 'Хаёт Хамраев', 'foreign_partner' => null, 'order' => null, 'notes' => 'Инфа от маркетинга'],
            ['direction' => 'inbound', 'name' => 'Визит сёмычный группы с Дубая', 'place' => 'ОАЭ Дубай', 'period' => 'Сентябрь', 'starts_month' => 9, 'people_count' => 6, 'people_note' => null, 'responsible' => 'Шерзод Султонов', 'curator' => 'Хаёт Хамраев', 'foreign_partner' => null, 'order' => null, 'notes' => 'Инфа от маркетинга'],
            ['direction' => 'inbound', 'name' => 'Пресс-туры для СМИ из Швеции', 'place' => 'Швеция', 'period' => 'октябрь- ноябрь', 'starts_month' => 10, 'people_count' => null, 'people_note' => null, 'responsible' => 'Национальный ПР центр', 'curator' => 'Хаёт Хамраев', 'foreign_partner' => null, 'order' => null, 'notes' => 'Из таблицы проектов. Ташвент, Самарканд, Бухара.'],
            ['direction' => 'inbound', 'name' => 'Совместный с Азербайджаном пресс-тур для блогеров и журналистов из Индии и Китая', 'place' => 'Индия и Китай', 'period' => 'сентябрь - декабрь', 'starts_month' => 9, 'people_count' => null, 'people_note' => null, 'responsible' => 'Национальный ПР центр', 'curator' => 'Хаёт Хамраев', 'foreign_partner' => 'ожидается меморандум', 'order' => null, 'notes' => 'Из таблицы проектов.'],
            ['direction' => 'local', 'name' => 'Хорезмская область — фестиваль «Праздник дыни»', 'place' => 'Харезм', 'period' => 'август', 'starts_month' => 8, 'people_count' => 36, 'people_note' => null, 'responsible' => 'Хаёт Хамраев', 'curator' => null, 'foreign_partner' => null, 'order' => '49-AF', 'notes' => 'По приказу 49Af'],
            ['direction' => 'local', 'name' => 'Кашкадарьинской области', 'place' => 'Кашкадаря', 'period' => 'август', 'starts_month' => 8, 'people_count' => 14, 'people_note' => null, 'responsible' => 'Хаёт Хамраев', 'curator' => null, 'foreign_partner' => null, 'order' => '49-AF', 'notes' => 'По приказу 49Af'],
            ['direction' => 'local', 'name' => 'Ташкентская область — фестиваль «Праздник винограда»', 'place' => 'Ташкентский област', 'period' => 'август', 'starts_month' => 8, 'people_count' => 14, 'people_note' => null, 'responsible' => 'Хаёт Хамраев', 'curator' => null, 'foreign_partner' => null, 'order' => '49-AF', 'notes' => 'По приказу 49Af'],
            ['direction' => 'local', 'name' => 'Хорезмская область — гастрономическое мероприятие «Национальные блюда ханов Хорезма»', 'place' => 'Харезм', 'period' => 'Сентябрь', 'starts_month' => 9, 'people_count' => 14, 'people_note' => null, 'responsible' => 'Хаёт Хамраев', 'curator' => null, 'foreign_partner' => null, 'order' => '49-AF', 'notes' => 'По приказу 49Af'],
            ['direction' => 'local', 'name' => 'Цифровое продвижение и коммуникационная кампания в СМИ Этно-деревней Навоийской области (пресс-тур)', 'place' => null, 'period' => 'октябрь', 'starts_month' => 10, 'people_count' => 14, 'people_note' => null, 'responsible' => 'Хаёт Хамраев', 'curator' => null, 'foreign_partner' => null, 'order' => '49-AF', 'notes' => 'По приказу 49Af'],
            ['direction' => 'local', 'name' => 'Пресс-тур на мероприятие "V Международный фестиваль мёда”', 'place' => 'Наманган', 'period' => 'октябрь', 'starts_month' => 10, 'people_count' => 14, 'people_note' => null, 'responsible' => 'Хаёт Хамраев', 'curator' => null, 'foreign_partner' => null, 'order' => '49-AF', 'notes' => 'По приказу 49Af'],
            ['direction' => 'local', 'name' => 'Пресс-тур - Охота и рыбалка', 'place' => 'Сырдаря', 'period' => 'Октябрь', 'starts_month' => 10, 'people_count' => 14, 'people_note' => null, 'responsible' => 'Хаёт Хамраев', 'curator' => null, 'foreign_partner' => null, 'order' => '49-AF', 'notes' => 'По приказу 49Af'],
            ['direction' => 'local', 'name' => 'Презентация туристического потенциала Самаркандской области', 'place' => 'Самарканд', 'period' => 'Октябрь', 'starts_month' => 10, 'people_count' => 14, 'people_note' => null, 'responsible' => 'Хаёт Хамраев', 'curator' => null, 'foreign_partner' => null, 'order' => '49-AF', 'notes' => 'По приказу 49Af'],
            ['direction' => 'local', 'name' => 'Презентация туристического потенциала города Ташкента', 'place' => 'Г.Ташкент', 'period' => 'Ноябрь', 'starts_month' => 11, 'people_count' => 14, 'people_note' => null, 'responsible' => 'Хаёт Хамраев', 'curator' => null, 'foreign_partner' => null, 'order' => '49-AF', 'notes' => 'По приказу 49Af'],
            ['direction' => 'local', 'name' => 'Презентация туристического потенциала Самаркандской области', 'place' => 'Самарканд', 'period' => 'Ноябрь', 'starts_month' => 11, 'people_count' => 14, 'people_note' => null, 'responsible' => 'Хаёт Хамраев', 'curator' => null, 'foreign_partner' => null, 'order' => '49-AF', 'notes' => 'По приказу 49Af'],
            ['direction' => 'local', 'name' => 'Презентация туристического потенциала Бухарской области', 'place' => 'Бухара', 'period' => 'Ноябрь', 'starts_month' => 11, 'people_count' => 14, 'people_note' => null, 'responsible' => 'Хаёт Хамраев', 'curator' => null, 'foreign_partner' => null, 'order' => '49-AF', 'notes' => 'По приказу 49Af'],
            ['direction' => 'local', 'name' => 'Презентация туристического потенциала Сурхандарьинской области', 'place' => 'Сурхандаря', 'period' => 'декабрь', 'starts_month' => 12, 'people_count' => 14, 'people_note' => null, 'responsible' => 'Хаёт Хамраев', 'curator' => null, 'foreign_partner' => null, 'order' => '49-AF', 'notes' => 'По приказу 49Af'],
            ['direction' => 'local', 'name' => 'Презентация туристического потенциала Кашкадарьинской области', 'place' => 'Кашкадаря', 'period' => 'декабрь', 'starts_month' => 12, 'people_count' => 14, 'people_note' => null, 'responsible' => 'Хаёт Хамраев', 'curator' => null, 'foreign_partner' => null, 'order' => '49-AF', 'notes' => 'По приказу 49Af'],
            ['direction' => 'outbound', 'name' => 'Пресс-тур Азербайджан', 'place' => 'Азербайджан', 'period' => 'август', 'starts_month' => 8, 'people_count' => null, 'people_note' => null, 'responsible' => 'Национальный ПР центр', 'curator' => 'Хаёт Хамраев', 'foreign_partner' => null, 'order' => null, 'notes' => 'Из таблицы проектов.'],
        ];
    }
}
