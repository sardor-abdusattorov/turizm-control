<?php

namespace Database\Seeders;

use App\Models\Contact;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    public function run(): void
    {
        $clients = [
            [
                'type' => Contact::TYPE_LEGAL,
                'name' => [
                    'ru' => 'ООО «Олтин Водий»',
                    'uz' => "\"Oltin Vodiy\" MChJ",
                    'en' => 'Oltin Vodiy LLC',
                ],
                'inn' => '301234567',
                'address' => [
                    'ru' => 'г. Ташкент, ул. Амира Темура, 15',
                    'uz' => "Toshkent sh., Amir Temur ko'ch., 15",
                    'en' => 'Tashkent, Amir Temur str., 15',
                ],
                'phone' => '+998 71 200-00-01',
                'email' => 'info@oltinvodiy.uz',
                'contact_person' => 'Aziz Karimov',
            ],
            [
                'type' => Contact::TYPE_LEGAL,
                'name' => [
                    'ru' => 'ЧП «Шёлковый путь»',
                    'uz' => "\"Ipak yo'li\" XK",
                    'en' => 'Silk Road Private Enterprise',
                ],
                'inn' => '302345678',
                'address' => [
                    'ru' => 'г. Самарканд, ул. Регистан, 7',
                    'uz' => "Samarqand sh., Registon ko'ch., 7",
                    'en' => 'Samarkand, Registan str., 7',
                ],
                'phone' => '+998 66 300-00-02',
                'email' => 'office@silkroad.uz',
                'contact_person' => 'Bobur Yusupov',
            ],
            [
                'type' => Contact::TYPE_LEGAL,
                'name' => [
                    'ru' => 'АО «Бухара Тур»',
                    'uz' => "\"Buxoro Tour\" AJ",
                    'en' => 'Bukhara Tour JSC',
                ],
                'inn' => '303456789',
                'address' => [
                    'ru' => 'г. Бухара, ул. Бахауддина Накшбанди, 42',
                    'uz' => "Buxoro sh., Bahouddin Naqshband ko'ch., 42",
                    'en' => 'Bukhara, Bahouddin Naqshband str., 42',
                ],
                'phone' => '+998 65 400-00-03',
                'email' => 'contact@bukharatour.uz',
                'contact_person' => 'Sevara Rakhimova',
            ],
            [
                'type' => Contact::TYPE_INDIVIDUAL,
                'name' => [
                    'ru' => 'Усманов Жасур Алишерович',
                    'uz' => 'Usmonov Jasur Alisherovich',
                    'en' => 'Usmanov Jasur Alisherovich',
                ],
                'inn' => '30101200012345',
                'address' => [
                    'ru' => 'г. Ташкент, мкр. Юнусабад-9, 12-15',
                    'uz' => "Toshkent sh., Yunusobod-9 mavzesi, 12-15",
                    'en' => 'Tashkent, Yunusabad-9 district, 12-15',
                ],
                'phone' => '+998 90 100-20-30',
                'email' => 'jasur.u@gmail.com',
            ],
        ];

        foreach ($clients as $data) {
            Contact::firstOrCreate(
                ['inn' => $data['inn']],
                array_merge($data, ['status' => true])
            );
        }
    }
}
