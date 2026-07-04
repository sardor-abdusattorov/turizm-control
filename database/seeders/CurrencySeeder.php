<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            [
                'short_name' => 'UZS',
                'name' => [
                    'ru' => 'Узбекский сум',
                    'uz' => "O'zbek so'mi",
                    'en' => 'Uzbek Soum',
                ],
                'value' => 1,
                'sort' => 1,
            ],
            [
                'short_name' => 'USD',
                'name' => [
                    'ru' => 'Доллар США',
                    'uz' => 'AQSh dollari',
                    'en' => 'US Dollar',
                ],
                'value' => 12500,
                'sort' => 2,
            ],
            [
                'short_name' => 'EUR',
                'name' => [
                    'ru' => 'Евро',
                    'uz' => 'Yevro',
                    'en' => 'Euro',
                ],
                'value' => 13500,
                'sort' => 3,
            ],
            [
                'short_name' => 'RUB',
                'name' => [
                    'ru' => 'Российский рубль',
                    'uz' => 'Rossiya rubli',
                    'en' => 'Russian Ruble',
                ],
                'value' => 135,
                'sort' => 4,
            ],
            [
                'short_name' => 'GBP',
                'name' => [
                    'ru' => 'Фунт стерлингов',
                    'uz' => 'Funt sterling',
                    'en' => 'Pound Sterling',
                ],
                'value' => 16500,
                'sort' => 5,
            ],
            // The currencies below cover the geography of the PR Centre's
            // exhibitions (China, UAE, Japan, Korea, India, Malaysia, Poland,
            // Türkiye, Kazakhstan, Azerbaijan). Seeded values are day-one
            // placeholders — the scheduled `currency:update` command refreshes
            // every active currency from cbu.uz daily.
            [
                'short_name' => 'CNY',
                'name' => [
                    'ru' => 'Китайский юань',
                    'uz' => 'Xitoy yuani',
                    'en' => 'Chinese Yuan',
                ],
                'value' => 1750,
                'sort' => 6,
            ],
            [
                'short_name' => 'AED',
                'name' => [
                    'ru' => 'Дирхам ОАЭ',
                    'uz' => 'BAA dirhami',
                    'en' => 'UAE Dirham',
                ],
                'value' => 3400,
                'sort' => 7,
            ],
            [
                'short_name' => 'JPY',
                'name' => [
                    'ru' => 'Японская иена',
                    'uz' => 'Yaponiya iyenasi',
                    'en' => 'Japanese Yen',
                ],
                'value' => 85,
                'sort' => 8,
            ],
            [
                'short_name' => 'KRW',
                'name' => [
                    'ru' => 'Южнокорейская вона',
                    'uz' => 'Janubiy Koreya voni',
                    'en' => 'South Korean Won',
                ],
                'value' => 9,
                'sort' => 9,
            ],
            [
                'short_name' => 'INR',
                'name' => [
                    'ru' => 'Индийская рупия',
                    'uz' => 'Hindiston rupiyasi',
                    'en' => 'Indian Rupee',
                ],
                'value' => 145,
                'sort' => 10,
            ],
            [
                'short_name' => 'MYR',
                'name' => [
                    'ru' => 'Малайзийский ринггит',
                    'uz' => 'Malayziya ringgiti',
                    'en' => 'Malaysian Ringgit',
                ],
                'value' => 2900,
                'sort' => 11,
            ],
            [
                'short_name' => 'PLN',
                'name' => [
                    'ru' => 'Польский злотый',
                    'uz' => 'Polsha zlotiyi',
                    'en' => 'Polish Zloty',
                ],
                'value' => 3300,
                'sort' => 12,
            ],
            [
                'short_name' => 'TRY',
                'name' => [
                    'ru' => 'Турецкая лира',
                    'uz' => 'Turkiya lirasi',
                    'en' => 'Turkish Lira',
                ],
                'value' => 350,
                'sort' => 13,
            ],
            [
                'short_name' => 'KZT',
                'name' => [
                    'ru' => 'Казахстанский тенге',
                    'uz' => 'Qozog\'iston tengesi',
                    'en' => 'Kazakhstani Tenge',
                ],
                'value' => 24,
                'sort' => 14,
            ],
            [
                'short_name' => 'AZN',
                'name' => [
                    'ru' => 'Азербайджанский манат',
                    'uz' => 'Ozarbayjon manati',
                    'en' => 'Azerbaijani Manat',
                ],
                'value' => 7350,
                'sort' => 15,
            ],
        ];

        foreach ($currencies as $data) {
            Currency::firstOrCreate(
                ['short_name' => $data['short_name']],
                [
                    'name' => $data['name'],
                    'value' => $data['value'],
                    'sort' => $data['sort'],
                    'status' => true,
                ]
            );
        }
    }
}
