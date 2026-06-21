<?php

namespace Database\Seeders;

use App\Models\Settings;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $values = [
            'organization.name.ru' => 'PR-Контроль',
            'organization.name.uz' => 'PR-Kontrol',
            'organization.name.en' => 'PR-Kontrol',

            'seo.title.ru' => 'PR-Контроль — система согласования договоров',
            'seo.title.uz' => 'PR-Kontrol — shartnomalarni kelishish tizimi',
            'seo.title.en' => 'PR-Kontrol — Contract Approval System',

            'seo.description.ru' => 'PR-Контроль — внутренняя система управления договорами Национального PR-центра Узбекистана: согласование, подписание, контроль оплат.',
            'seo.description.uz' => 'PR-Kontrol — Oʻzbekiston Milliy PR-markazining shartnomalarni boshqarish ichki tizimi: kelishuv, imzolash, toʻlovlar nazorati.',
            'seo.description.en' => 'PR-Kontrol — internal contract management system of the National PR Center of Uzbekistan: approval, signing, payment tracking.',

            'seo.keywords.ru' => 'PR-Контроль, согласование договоров, контроль договоров, PR-центр Узбекистан',
            'seo.keywords.uz' => 'PR-Kontrol, shartnoma kelishish, shartnoma nazorati, Milliy PR-markaz',
            'seo.keywords.en' => 'PR-Kontrol, contract approval, contract management, National PR Center',

            'seo.indexing_enabled' => true,

            'metrics.yandex' => '',
            'metrics.google' => '',

            'approval.flow' => ['legal', 'accounting'],
            'approval.sla_days' => 2,
        ];

        foreach ($values as $key => $value) {
            Settings::set($key, $value);
        }
    }
}
