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
                    'uz' => '"Oltin Vodiy" MChJ',
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
                    'uz' => '"Buxoro Tour" AJ',
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
                'pinfl' => '30101200012345',
                'address' => [
                    'ru' => 'г. Ташкент, мкр. Юнусабад-9, 12-15',
                    'uz' => 'Toshkent sh., Yunusobod-9 mavzesi, 12-15',
                    'en' => 'Tashkent, Yunusabad-9 district, 12-15',
                ],
                'phone' => '+998 90 100-20-30',
                'email' => 'jasur.u@gmail.com',
            ],
            [
                'type' => Contact::TYPE_LEGAL,
                'name' => ['ru' => 'ООО «Зарафшан Трэвел»', 'uz' => '"Zarafshon Travel" MChJ', 'en' => 'Zarafshan Travel LLC'],
                'inn' => '304567890',
                'address' => ['ru' => 'г. Навои, ул. Алишера Навои, 23', 'uz' => "Navoiy sh., Alisher Navoiy ko'ch., 23", 'en' => 'Navoi, Alisher Navoi str., 23'],
                'phone' => '+998 79 500-00-05',
                'email' => 'info@zarafshantravel.uz',
                'contact_person' => 'Dilshod Toirov',
            ],
            [
                'type' => Contact::TYPE_LEGAL,
                'name' => ['ru' => 'ООО «Хива Караван»', 'uz' => '"Xiva Karvon" MChJ', 'en' => 'Khiva Caravan LLC'],
                'inn' => '305678901',
                'address' => ['ru' => 'г. Хива, ул. Ичан-Кала, 4', 'uz' => "Xiva sh., Ichan-Qal'a ko'ch., 4", 'en' => 'Khiva, Ichan-Qala str., 4'],
                'phone' => '+998 62 600-00-06',
                'email' => 'office@khivacaravan.uz',
                'contact_person' => 'Nodira Allayarova',
            ],
            [
                'type' => Contact::TYPE_LEGAL,
                'name' => ['ru' => 'ЧП «Фергана Гид Сервис»', 'uz' => "\"Farg'ona Gid Servis\" XK", 'en' => 'Fergana Guide Service'],
                'inn' => '306789012',
                'address' => ['ru' => 'г. Фергана, ул. Мустакиллик, 88', 'uz' => "Farg'ona sh., Mustaqillik ko'ch., 88", 'en' => 'Fergana, Mustaqillik str., 88'],
                'phone' => '+998 73 700-00-07',
                'email' => 'guide@ferganaservice.uz',
                'contact_person' => 'Sherzod Komilov',
            ],
            [
                'type' => Contact::TYPE_LEGAL,
                'name' => ['ru' => 'АО «Чарвак Резорт»', 'uz' => '"Chorvoq Resort" AJ', 'en' => 'Charvak Resort JSC'],
                'inn' => '307890123',
                'address' => ['ru' => 'Ташкентская обл., Бостанлыкский р-н, Чарвак', 'uz' => 'Toshkent vil., Bo‘stonliq tumani, Chorvoq', 'en' => 'Tashkent region, Bostanliq district, Charvak'],
                'phone' => '+998 70 800-00-08',
                'email' => 'booking@charvakresort.uz',
                'contact_person' => 'Kamola Inoyatova',
            ],
            [
                'type' => Contact::TYPE_LEGAL,
                'name' => ['ru' => 'ООО «Самарканд Сити Транс»', 'uz' => '"Samarqand City Trans" MChJ', 'en' => 'Samarkand City Trans LLC'],
                'inn' => '308901234',
                'address' => ['ru' => 'г. Самарканд, ул. Гагарина, 145', 'uz' => "Samarqand sh., Gagarin ko'ch., 145", 'en' => 'Samarkand, Gagarin str., 145'],
                'phone' => '+998 66 900-00-09',
                'email' => 'transport@samcitytrans.uz',
                'contact_person' => 'Jamshid Ortiqov',
            ],
            [
                'type' => Contact::TYPE_INDIVIDUAL,
                'name' => ['ru' => 'Каримова Дилноза Фарходовна', 'uz' => 'Karimova Dilnoza Farhodovna', 'en' => 'Karimova Dilnoza Farhodovna'],
                'pinfl' => '31502199012345',
                'address' => ['ru' => 'г. Ташкент, мкр. Чиланзар-12, 5-44', 'uz' => 'Toshkent sh., Chilonzor-12 mavzesi, 5-44', 'en' => 'Tashkent, Chilanzar-12 district, 5-44'],
                'phone' => '+998 93 200-40-50',
                'email' => 'dilnoza.k@gmail.com',
            ],
        ];

        foreach ($clients as $data) {
            $key = $data['type'] === Contact::TYPE_LEGAL
                ? ['inn' => $data['inn'] ?? null]
                : ['pinfl' => $data['pinfl'] ?? null];

            Contact::firstOrCreate(
                $key,
                array_merge($data, ['status' => true])
            );
        }
    }
}
