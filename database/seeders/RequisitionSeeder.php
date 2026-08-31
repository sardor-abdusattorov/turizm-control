<?php

namespace Database\Seeders;

use App\Enums\RequisitionStatus;
use App\Models\Project;
use App\Models\Requisition;
use App\Models\User;
use App\Services\Approvals\ApprovalChain;
use App\Services\Approvals\ApprovalWorkflow;
use Illuminate\Database\Seeder;

class RequisitionSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::firstWhere('email', 'manager@test.uz');
        $legal = User::firstWhere('email', 'legal@test.uz');
        $accounting = User::firstWhere('email', 'accounting@test.uz');
        $director = User::firstWhere('email', 'director@test.uz');

        if (! $author || ! $legal || ! $accounting || ! $director) {
            $this->command?->warn('RequisitionSeeder skipped: test users are missing.');

            return;
        }

        $chain = app(ApprovalChain::class);
        $workflow = app(ApprovalWorkflow::class);

        foreach ($this->requisitions() as $data) {
            if (Requisition::query()->where('title', $data['title'])->exists()) {
                continue;
            }

            $approvers = match ($data['stage']) {
                'approved' => [$legal->id, $accounting->id],
                default => [$legal->id, $accounting->id, $director->id],
            };

            $requisition = Requisition::query()->create([
                'number' => Requisition::nextNumber(),
                'title' => $data['title'],
                'description' => $data['description'],
                'project_id' => Project::query()->inRandomOrder()->value('id'),
                'author_id' => $author->id,
                'status' => RequisitionStatus::Draft,
            ]);

            $chain->sync($requisition, $approvers);

            if ($data['stage'] === 'draft') {
                continue;
            }

            $workflow->submit($requisition);

            match ($data['stage']) {
                'approved' => $this->approveAll($workflow, $requisition, [$legal, $accounting]),
                'rejected' => $workflow->reject(
                    $requisition->refresh(),
                    $legal,
                    'Смета превышает лимит, утверждённый на квартал. Нужен пересчёт по позициям аренды.',
                ),
                default => $workflow->approve($requisition->refresh(), $legal, 'Замечаний нет.'),
            };
        }
    }

    /** @param  list<User>  $approvers */
    private function approveAll(ApprovalWorkflow $workflow, Requisition $requisition, array $approvers): void
    {
        foreach ($approvers as $approver) {
            $workflow->approve($requisition->refresh(), $approver);
        }
    }

    /** @return list<array{title: string, description: string, stage: string}> */
    private function requisitions(): array
    {
        return [
            [
                'title' => 'Закупка выставочного оборудования для стенда ATM Dubai',
                'description' => "Требуется закупить модульные стеновые панели, подсветку и стойку ресепшн для национального стенда.\n\nСмета: 42 800 000 сум. Поставка до 15 марта, монтаж силами подрядчика.",
                'stage' => 'approved',
            ],
            [
                'title' => 'Перевод и печать каталога «Узбекистан — открытая страна»',
                'description' => "Перевод каталога на английский и арабский, вёрстка и печать тиража 3 000 экземпляров.\n\nТираж делится поровну между выставками ATM Dubai и WTM London.",
                'stage' => 'in_review',
            ],
            [
                'title' => 'Аренда транспорта для пресс-тура по Самарканду и Бухаре',
                'description' => "Два микроавтобуса на 6 дней с водителями, включая топливо и парковку.\n\nГруппа: 14 журналистов из Германии, Франции и Южной Кореи.",
                'stage' => 'in_review',
            ],
            [
                'title' => 'Продление лицензий на фотобанк и видеомонтаж',
                'description' => "Годовая подписка на фотобанк и пакет лицензий для монтажа промо-роликов.\n\nТекущие лицензии истекают в конце месяца.",
                'stage' => 'rejected',
            ],
            [
                'title' => 'Закупка сувенирной продукции к WTM London',
                'description' => "Брендированные блокноты, флешки и упаковка для подарочных наборов.\n\nОриентировочный объём — 500 наборов, финальные макеты в работе.",
                'stage' => 'draft',
            ],
        ];
    }
}
