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

        $this->pruneRetiredCurrencies();
    }

    /**
     * An earlier revision seeded ten extra exhibition-geography currencies;
     * the registry only needs the core five. Remove the retired codes from
     * existing installs — but never a currency that is already referenced by
     * a contract, project or participant row.
     */
    private function pruneRetiredCurrencies(): void
    {
        $retired = ['CNY', 'AED', 'JPY', 'KRW', 'INR', 'MYR', 'PLN', 'TRY', 'KZT', 'AZN'];

        Currency::query()
            ->whereIn('short_name', $retired)
            ->whereNotIn('id', fn ($query) => $query->select('currency_id')
                ->from('contracts')
                ->whereNotNull('currency_id'))
            ->whereNotIn('id', fn ($query) => $query->select('currency_id')
                ->from('project_participants')
                ->whereNotNull('currency_id'))
            ->whereNotIn('id', fn ($query) => $query->select('area_currency_id')
                ->from('projects')
                ->whereNotNull('area_currency_id'))
            ->whereNotIn('id', fn ($query) => $query->select('stand_currency_id')
                ->from('projects')
                ->whereNotNull('stand_currency_id'))
            ->delete();
    }
}
