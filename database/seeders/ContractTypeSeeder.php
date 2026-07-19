<?php

namespace Database\Seeders;

use App\Enums\ContractDirection;
use App\Enums\CounterpartyKind;
use App\Models\ContractType;
use Illuminate\Database\Seeder;

/**
 * The six contract kinds distilled from the real 2025 dossiers: every one of
 * the 26 scanned expense contracts is a space rental, a stand construction,
 * a service or an agency deal; the income side is participant fees and
 * sponsorship from the exhibition registries.
 */
class ContractTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'title' => ['ru' => 'Аренда площади', 'uz' => 'Maydon ijarasi', 'en' => 'Exhibition space rental'],
                'description' => [
                    'ru' => 'Аренда выставочной площади у организатора или через посредника',
                    'uz' => "Tashkilotchidan yoki vositachi orqali ko'rgazma maydonini ijaraga olish",
                    'en' => 'Renting exhibition space from the organiser or via an intermediary',
                ],
                'direction' => ContractDirection::Expense,
                'sort' => 1,
            ],
            [
                'title' => ['ru' => 'Застройка стенда', 'uz' => 'Stend qurish', 'en' => 'Stand construction'],
                'description' => [
                    'ru' => 'Готовый выставочный стенд: монтаж и демонтаж по утверждённым эскизам',
                    'uz' => "Tayyor ko'rgazma stendi: tasdiqlangan eskizlar bo'yicha montaj va demontaj",
                    'en' => 'Turnkey exhibition stand: installation and dismantling to approved sketches',
                ],
                'direction' => ContractDirection::Expense,
                'sort' => 2,
            ],
            [
                // We render the service, the participant pays — the type the
                // hand-entered national-stand contracts actually need.
                'title' => ['ru' => 'Оказание услуг', 'uz' => "Xizmat ko'rsatish", 'en' => 'Services'],
                'description' => [
                    'ru' => 'Услуги участникам: организация участия на национальном стенде',
                    'uz' => 'Ishtirokchilarga xizmatlar: milliy stendda ishtirokni tashkil etish',
                    'en' => 'Services to participants: organising participation on the national stand',
                ],
                'direction' => ContractDirection::Income,
                'sort' => 5,
            ],
            [
                'title' => ['ru' => 'Услуги подрядчика', 'uz' => 'Pudratchi xizmatlari', 'en' => 'Contractor services'],
                'description' => [
                    'ru' => 'Услуги, которые оплачиваем мы: роудшоу, транспорт, оборудование',
                    'uz' => "Biz to'laydigan xizmatlar: roudshou, transport, jihozlar",
                    'en' => 'Services we pay for: roadshows, transport, equipment',
                ],
                'direction' => ContractDirection::Expense,
                'sort' => 3,
            ],
            [
                'title' => ['ru' => 'Агентские услуги', 'uz' => 'Agentlik xizmatlari', 'en' => 'Agency services'],
                'description' => [
                    'ru' => 'Содействие в расчётах: транзит платежа организатору через агента',
                    'uz' => "Hisob-kitoblarda ko'maklashish: to'lovni agent orqali tashkilotchiga o'tkazish",
                    'en' => 'Payment facilitation: transferring funds to the organiser via an agent',
                ],
                'direction' => ContractDirection::Expense,
                'sort' => 4,
            ],
            [
                'title' => ['ru' => 'Взнос участника', 'uz' => 'Ishtirokchi badali', 'en' => 'Participant fee'],
                'description' => [
                    'ru' => 'Взнос туроператора за участие в выставке на национальном стенде',
                    'uz' => "Turoperatorning milliy stendda ko'rgazmada ishtirok etish badali",
                    'en' => "Tour operator's fee for participating on the national stand",
                ],
                'direction' => ContractDirection::Income,
                'sort' => 6,
            ],
            [
                'title' => ['ru' => 'Спонсорство', 'uz' => 'Homiylik', 'en' => 'Sponsorship'],
                'description' => [
                    'ru' => 'Спонсорский вклад в проект (например, Uzbekistan Airways)',
                    'uz' => 'Loyihaga homiylik hissasi (masalan, Uzbekistan Airways)',
                    'en' => 'Sponsor contribution to a project (e.g. Uzbekistan Airways)',
                ],
                'direction' => ContractDirection::Income,
                'counterparty_kind' => CounterpartyKind::Sponsor,
                'sort' => 7,
            ],
        ];

        foreach ($types as $data) {
            ContractType::updateOrCreate(
                ['title->ru' => $data['title']['ru']],
                [
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'direction' => $data['direction']->value,
                    'counterparty_kind' => ($data['counterparty_kind'] ?? CounterpartyKind::Contact)->value,
                    'sort' => $data['sort'],
                    'status' => true,
                ]
            );
        }
    }
}
