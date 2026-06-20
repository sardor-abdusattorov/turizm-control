<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderType;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrdersSeeder extends Seeder
{
    public function run(): void
    {
        $hr = OrderType::firstWhere('title->ru', 'Кадровый');
        $financial = OrderType::firstWhere('title->ru', 'Финансовый');
        $admin = OrderType::firstWhere('title->ru', 'Хозяйственный');
        $production = OrderType::firstWhere('title->ru', 'Производственный');

        if (! $hr || ! $financial || ! $admin || ! $production) {
            $this->command?->warn('OrdersSeeder skipped: run OrderTypeSeeder first.');

            return;
        }

        $manager = User::firstWhere('email', 'manager@test.uz');
        $lawyer = User::firstWhere('email', 'legal@test.uz');
        $accountant = User::firstWhere('email', 'accounting@test.uz');
        $fallback = User::firstWhere('email', 'mr.silverwind1998@gmail.com') ?? User::first();

        $creatorFor = fn (?User $user) => ($user ?? $fallback)?->id;

        if (! $fallback) {
            $this->command?->warn('OrdersSeeder skipped: no users found.');

            return;
        }

        $endOfYear = now()->endOfYear();
        $clampDeadline = fn ($date) => $date->greaterThan($endOfYear) ? $endOfYear : $date;

        $orders = [
            [
                'type' => $hr,
                'title' => 'О приёме на работу Карима Усманова',
                'description' => 'Принять Усманова Карима Алишеровича на должность разработчика в IT отдел с 1 числа следующего месяца.',
                'deadline' => $clampDeadline(now()->addWeeks(2)),
                'creator' => $manager,
                'status' => true,
            ],
            [
                'type' => $financial,
                'title' => 'О выплате премии по итогам квартала',
                'description' => 'Утвердить выплату квартальной премии сотрудникам согласно приложению.',
                'deadline' => $clampDeadline(now()->addDays(7)),
                'creator' => $accountant,
                'status' => true,
            ],
            [
                'type' => $admin,
                'title' => 'Об организации корпоративного мероприятия',
                'description' => 'Организовать корпоратив для сотрудников компании 25 числа.',
                'deadline' => $clampDeadline(now()->addMonth()),
                'creator' => $manager,
                'status' => true,
            ],
            [
                'type' => $production,
                'title' => 'О закупке производственного оборудования',
                'description' => 'Закупить оборудование для расширения производственных мощностей согласно смете.',
                'deadline' => $clampDeadline(now()->addWeeks(3)),
                'creator' => $fallback,
                'status' => true,
            ],
            [
                'type' => $hr,
                'title' => 'О переводе сотрудника в финансовый отдел',
                'description' => 'Перевести Петрова И.И. с должности специалиста IT отдела на должность специалиста финансового отдела.',
                'deadline' => $clampDeadline(now()->addDays(3)),
                'creator' => $lawyer,
                'status' => true,
            ],
            [
                'type' => $financial,
                'title' => 'О дисциплинарном взыскании',
                'description' => 'Объявить выговор за нарушение трудовой дисциплины.',
                'deadline' => $clampDeadline(now()->addDay()),
                'creator' => $lawyer,
                'status' => true,
            ],
            [
                'type' => $admin,
                'title' => 'О проведении генеральной уборки',
                'description' => 'Провести генеральную уборку офисных помещений в выходные дни.',
                'deadline' => $clampDeadline(now()->addDays(10)),
                'creator' => $manager,
                'status' => false,
            ],
            [
                'type' => $hr,
                'title' => 'Об отпуске генерального директора',
                'description' => 'Предоставить ежегодный оплачиваемый отпуск с 15 числа.',
                'deadline' => $clampDeadline(now()->addWeeks(4)),
                'creator' => $accountant,
                'status' => true,
            ],
        ];

        foreach ($orders as $data) {
            // The seed dataset still carries a `deadline` per row from the old
            // schema — reuse it as a rough "issued at" anchor (the order would
            // have been signed shortly before the work needed doing).
            $issued = $data['deadline']->copy()->subDays(rand(7, 60));

            Order::firstOrCreate(
                [
                    'order_type_id' => $data['type']->id,
                    'title' => $data['title'],
                ],
                [
                    'description' => $data['description'],
                    'file_path' => 'uploads/files/orders/'.now()->format('Y/m').'/sample.pdf',
                    'issued_at' => $issued,
                    'status' => $data['status'],
                    'created_by' => $creatorFor($data['creator']),
                ]
            );
        }
    }
}
