<?php

namespace Database\Seeders;

use App\Models\Settings;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $values = [
            'organization.name.ru' => 'Национальный PR-центр',
            'organization.name.uz' => 'Milliy PR-markazi',
            'organization.name.en' => 'National PR Center',

            'seo.title.ru' => 'Национальный PR-центр Узбекистана',
            'seo.title.uz' => "O'zbekiston Milliy PR-markazi",
            'seo.title.en' => 'National PR Center of Uzbekistan',

            'seo.description.ru' => 'Национальный PR-центр презентует туристический потенциал Узбекистана на мировой арене: международные мероприятия, медиа-проекты и продвижение туризма.',
            'seo.description.uz' => "Milliy PR-markazi O'zbekiston turizm salohiyatini jahon miqyosida namoyish etadi: xalqaro tadbirlar, media-loyihalar va turizmni rivojlantirish.",
            'seo.description.en' => "The National PR Center presents Uzbekistan's tourism potential on the global stage: international events, media projects and tourism promotion.",

            'seo.keywords.ru' => 'туризм Узбекистан, PR-центр, продвижение туризма, медиа-проекты',
            'seo.keywords.uz' => "O'zbekiston turizmi, PR-markaz, turizmni rivojlantirish",
            'seo.keywords.en' => 'Uzbekistan tourism, PR center, tourism promotion',

            'seo.indexing_enabled' => true,

            'metrics.yandex' => '',
            'metrics.google' => '',

            'approval.flow' => ['legal', 'financial', 'accounting', 'direction'],
        ];

        foreach ($values as $key => $value) {
            Settings::set($key, $value);
        }
    }
}
