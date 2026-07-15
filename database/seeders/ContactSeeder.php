<?php

namespace Database\Seeders;

use App\Models\Contact;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    public function run(): void
    {
        $contacts = [
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «BEYOND DMC»',
                    'uz' => '"BEYOND DMC" MChJ',
                    'en' => 'BEYOND DMC LLC',
                ],
                'inn' => '311687587',
                'address' => [
                    'ru' => 'Республика Узбекистан, г. Ташкент, Яккасарайский район, МСГ «Ракатбоши», ул. Баходир, 44А',
                    'uz' => 'Oʻzbekiston Respublikasi, Toshkent shahri, Yakkasaroy tumani, "Rakatboshi" MFY, Bahodir koʻchasi, 44A',
                    'en' => '44A Bahodir Street, Rakatboshi Mahalla, Yakkasaray District, Tashkent, Republic of Uzbekistan',
                ],
                'phone' => '+998 99 235 48 36, +998 94 092 11 75',
                'email' => 'info@beyond-dmc.com',
                'director_name' => 'Rajesh Kumar',
                'bank_account' => '20208000907160357001',
                'bank_name' => 'ATB "Uzsanoatqurilishbank", Rakat Branch',
                'swift' => 'USJIUZ22',
            ],
        ];

        foreach ($contacts as $data) {
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
