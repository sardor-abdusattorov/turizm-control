<?php

namespace Database\Seeders;

use App\Enums\ContractApproverStatus;
use App\Enums\ContractStatus;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\ContractAttachment;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ContractSeeder extends Seeder
{
    public function run(): void
    {
        $manager = User::firstWhere('email', 'manager@test.uz');
        $legal = User::firstWhere('email', 'legal@test.uz');
        $accountant = User::firstWhere('email', 'accounting@test.uz');
        $director = User::firstWhere('email', 'director@test.uz');

        if (! $manager) {
            $this->command?->warn('ContractSeeder skipped: TestUsersSeeder must run first.');

            return;
        }

        $currencies = Currency::query()->get()->keyBy('short_name');
        $contacts = Contact::query()->orderBy('id')->get();
        $types = ContractType::query()->where('status', true)->orderBy('sort')->get();

        if ($currencies->isEmpty() || $contacts->isEmpty() || $types->isEmpty()) {
            $this->command?->warn('ContractSeeder skipped: currencies, contacts and contract types must exist first.');

            return;
        }

        $chain = ['legal' => $legal, 'accounting' => $accountant, 'director' => $director];
        $paymentAuthor = $accountant ?? $manager;

        $specs = array_merge(
            $this->curatedContracts($currencies, $types, $contacts),
            $this->generatedContracts($currencies, $types, $contacts),
        );

        foreach ($specs as $index => $spec) {
            $number = 'DEMO-'.now()->year.'-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            $this->seedContract($number, $spec, $manager, $paymentAuthor, $chain);
        }
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array{legal: ?User, accounting: ?User, director: ?User}  $chain
     */
    private function seedContract(
        string $number,
        array $spec,
        User $manager,
        User $paymentAuthor,
        array $chain,
    ): void {
        /** @var ContractStatus $status */
        $status = $spec['status'];
        $createdAt = $spec['created_at'];
        $signedAt = $spec['signed_at'] ?? null;

        $contract = Contract::firstOrCreate(
            ['number' => $number],
            [
                'title' => $spec['title'],
                'contract_type_id' => $spec['type']->id,
                'contact_id' => $spec['contact']->id,
                'currency_id' => $spec['currency']->id,
                'responsible_id' => $manager->id,
                'amount' => $spec['amount'],
                'status' => Contract::STATUS_DRAFT,
            ]
        );

        if (! $contract->wasRecentlyCreated) {
            return;
        }

        $this->seedDossier($contract, $manager);

        Contract::query()->whereKey($contract->id)->update([
            'status' => $status->value,
            'signed_at' => $signedAt?->toDateString(),
            'created_at' => $createdAt,
            'updated_at' => $signedAt ?? $createdAt,
        ]);

        $contract->refresh();

        $this->seedApprovers($contract, $chain, $status, (bool) ($spec['overdue'] ?? false), $createdAt);
        $this->seedPayments($contract, $paymentAuthor, $spec['currency']->short_name, $spec['payments'] ?? [], $signedAt ?? $createdAt);
    }

    /** @param  array{legal: ?User, accounting: ?User, director: ?User}  $chain */
    private function seedApprovers(Contract $contract, array $chain, ContractStatus $status, bool $overdue, Carbon $createdAt): void
    {
        ['legal' => $legal, 'accounting' => $accounting, 'director' => $director] = $chain;

        $approved = ContractApprover::STATUS_APPROVED;
        $rejected = ContractApprover::STATUS_REJECTED;
        $pending = ContractApprover::STATUS_PENDING;
        $queued = ContractApprover::STATUS_QUEUED;

        $steps = match ($status) {
            ContractStatus::Draft => [[$legal, $queued], [$accounting, $queued]],
            ContractStatus::InReview => [[$legal, $pending], [$accounting, $queued]],
            ContractStatus::PendingDirector => [[$legal, $approved], [$accounting, $approved]],
            ContractStatus::InReviewDirector => [[$legal, $approved], [$accounting, $approved], [$director, $pending]],
            ContractStatus::Approved => [[$legal, $approved], [$accounting, $approved], [$director, $approved]],
            ContractStatus::Rejected => $contract->id % 2 === 0
                ? [[$legal, $rejected]]
                : [[$legal, $approved], [$accounting, $rejected]],
        };

        $order = 1;

        foreach ($steps as [$user, $approverStatus]) {
            if (! $user) {
                continue;
            }

            $this->writeApprover($contract, $user, $order++, $approverStatus, $overdue, $createdAt);
        }
    }

    private function writeApprover(
        Contract $contract,
        User $user,
        int $order,
        ContractApproverStatus $status,
        bool $overdue,
        Carbon $createdAt,
    ): void {
        $hasVerdict = in_array($status, [ContractApprover::STATUS_APPROVED, ContractApprover::STATUS_REJECTED], true);

        $comment = match ($status) {
            ContractApprover::STATUS_APPROVED => match ($order) {
                1 => 'Согласовано с юридической стороны, замечаний нет.',
                2 => 'Финансовых возражений нет, сумма подтверждена.',
                default => 'Утверждаю. Договор можно подписывать.',
            },
            ContractApprover::STATUS_REJECTED => 'Прошу уточнить сумму НДС в п. 3.2 и приложить смету расходов.',
            default => null,
        };

        $actedAt = $hasVerdict ? $createdAt->copy()->addDays($order * 2) : null;

        $dueAt = match (true) {
            $status === ContractApprover::STATUS_PENDING => $overdue ? now()->subDays(2) : now()->addDays(2),
            $actedAt !== null => $actedAt->copy()->addDays(($contract->id + $order) % 5 === 0 ? -2 : 2),
            default => null,
        };

        ContractApprover::create([
            'contract_id' => $contract->id,
            'user_id' => $user->id,
            'order' => $order,
            'status' => $status,
            'comment' => $comment,
            'acted_at' => $actedAt,
            'due_at' => $dueAt,
        ]);
    }

    /** @param  list<int>  $percentages */
    private function seedPayments(Contract $contract, User $author, string $currencyShort, array $percentages, Carbon $baseDate): void
    {
        $amountLabel = number_format((float) $contract->amount, 0, '.', ' ').' '.$currencyShort;

        foreach (array_values($percentages) as $index => $percent) {
            $paidAt = $baseDate->copy()->addDays(12 + $index * 18);

            if ($paidAt->greaterThan(now())) {
                $paidAt = now()->subDays(1 + $index);
            }

            Payment::create([
                'contract_id' => $contract->id,
                'created_by' => $author->id,
                'percent' => $percent,
                'paid_at' => $paidAt,
                'screenshots' => [DemoMedia::paymentReceipt(
                    $contract->number,
                    $amountLabel,
                    $percent.'%',
                    $paidAt->format('d.m.Y'),
                )],
            ]);
        }
    }

    private function seedDossier(Contract $contract, User $uploader): void
    {
        $path = 'uploads/files/contract-attachments/demo/'.$contract->id.'/scan.pdf';
        $body = "%PDF-1.4\n% ".$contract->number.' — '.$contract->title."\n%%EOF\n";

        Storage::disk('local')->put($path, $body);

        ContractAttachment::create([
            'contract_id' => $contract->id,
            'file_path' => $path,
            'original_name' => $contract->number.'.pdf',
            'size' => strlen($body),
            'uploaded_by' => $uploader->id,
            'sort' => 1,
        ]);
    }

    /**
     * @param  Collection<string, Currency>  $currencies
     * @param  Collection<int, ContractType>  $types
     * @param  Collection<int, Contact>  $contacts
     * @return array<int, array<string, mixed>>
     */
    private function curatedContracts(Collection $currencies, Collection $types, Collection $contacts): array
    {
        $uzs = $currencies->get('UZS') ?? $currencies->first();
        $usd = $currencies->get('USD') ?? $uzs;

        $rent = $types->first(fn (ContractType $t) => str_contains(mb_strtolower($t->title), 'аренд')) ?? $types->first();
        $service = $types->first(fn (ContractType $t) => str_contains(mb_strtolower($t->title), 'услуг')) ?? $types->first();
        $other = $types->last() ?? $types->first();
        $individual = $contacts->firstWhere('type', Contact::TYPE_INDIVIDUAL) ?? $contacts->last();

        return [
            [
                'title' => 'Аренда офисного помещения на 2026 год',
                'amount' => 180_000_000,
                'currency' => $uzs,
                'type' => $rent,
                'contact' => $contacts->first(),
                'status' => ContractStatus::Approved,
                'created_at' => now()->subMonths(5)->startOfMonth()->addDays(8),
                'signed_at' => now()->subMonths(4)->startOfMonth()->addDays(12),
                'payments' => [40, 60],
            ],
            [
                'title' => 'Оказание услуг по организации туров',
                'amount' => 25_000,
                'currency' => $usd,
                'type' => $service,
                'contact' => $contacts->skip(1)->first() ?? $contacts->first(),
                'status' => ContractStatus::Approved,
                'created_at' => now()->subMonths(3)->startOfMonth()->addDays(5),
                'signed_at' => now()->subMonths(2)->startOfMonth()->addDays(10),
                'payments' => [30],
            ],
            [
                'title' => 'Услуги транспортной логистики',
                'amount' => 95_000_000,
                'currency' => $uzs,
                'type' => $service,
                'contact' => $contacts->skip(2)->first() ?? $contacts->first(),
                'status' => ContractStatus::InReview,
                'created_at' => now()->subMonths(1)->startOfMonth()->addDays(14),
                'overdue' => true,
            ],
            [
                'title' => 'Маркетинговое сопровождение тура',
                'amount' => 12_500_000,
                'currency' => $uzs,
                'type' => $service,
                'contact' => $contacts->first(),
                'status' => ContractStatus::Draft,
                'created_at' => now()->subDays(14),
            ],
            [
                'title' => 'Договор с физлицом-гидом',
                'amount' => 8_000_000,
                'currency' => $uzs,
                'type' => $other,
                'contact' => $individual,
                'status' => ContractStatus::Rejected,
                'created_at' => now()->subMonths(4)->startOfMonth()->addDays(20),
            ],
        ];
    }

    /**
     * @param  Collection<string, Currency>  $currencies
     * @param  Collection<int, ContractType>  $types
     * @param  Collection<int, Contact>  $contacts
     * @return array<int, array<string, mixed>>
     */
    private function generatedContracts(Collection $currencies, Collection $types, Collection $contacts): array
    {
        $titles = [
            'Бронирование гостиничных номеров в Самарканде',
            'Аренда туристических автобусов',
            'Экскурсионное обслуживание по Бухаре',
            'Авиабилеты для туристической группы',
            'Услуги гида-переводчика (английский язык)',
            'Организация питания туристов',
            'Страхование участников тура',
            'Визовая поддержка иностранных туристов',
            'Трансфер аэропорт — отель',
            'Аренда конференц-зала для семинара',
            'Рекламное продвижение туров в соцсетях',
            'Печать рекламных буклетов и каталогов',
            'Услуги профессионального фотографа',
            'Техническое сопровождение сайта',
            'Поставка сувенирной продукции',
            'Кейтеринг для корпоративного мероприятия',
            'Аренда юрты для этно-тура',
            'Организация речного круиза',
            'Билеты в музеи и на культурные объекты',
            'Услуги переводчика для деловой встречи',
            'Аренда внедорожников для горного тура',
            'Бронирование санатория «Чарвак»',
            'Организация мастер-класса по плову',
            'Услуги SMM-агентства на квартал',
            'Поставка питьевой воды для туров',
            'Медицинское сопровождение тургруппы',
            'Аренда теплохода на Айдаркуле',
            'Прокат туристического снаряжения',
            'Услуги клининга в гостевом доме',
            'Организация фольклорного концерта',
            'Поставка форменной одежды для гидов',
            'Чартерный авиарейс Ташкент — Стамбул',
        ];

        $statusPlan = [
            ContractStatus::Approved, ContractStatus::Approved, ContractStatus::Approved,
            ContractStatus::Rejected, ContractStatus::Approved, ContractStatus::Approved,
            ContractStatus::Approved, ContractStatus::Approved, ContractStatus::Rejected,
            ContractStatus::Approved, ContractStatus::Approved, ContractStatus::PendingDirector,
            ContractStatus::Approved, ContractStatus::Approved, ContractStatus::InReviewDirector,
            ContractStatus::Approved, ContractStatus::PendingDirector, ContractStatus::Approved,
            ContractStatus::Approved, ContractStatus::InReview, ContractStatus::InReviewDirector,
            ContractStatus::Approved, ContractStatus::InReview, ContractStatus::PendingDirector,
            ContractStatus::InReview, ContractStatus::Approved, ContractStatus::InReview,
            ContractStatus::Draft, ContractStatus::InReviewDirector, ContractStatus::InReview,
            ContractStatus::Draft, ContractStatus::Draft,
        ];

        $specs = [];

        foreach ($titles as $index => $title) {
            $status = $statusPlan[$index];
            $currency = $this->pickCurrency($currencies, $index);

            $monthsAgo = max(0, 5 - intdiv($index, 6));
            $createdAt = now()->subMonths($monthsAgo)->startOfMonth()->addDays(($index * 7) % 24 + 1);

            $signedAt = null;
            $payments = [];

            if ($status === ContractStatus::Approved) {
                $signedAt = $createdAt->copy()->addDays(7 + $index % 9);

                if ($signedAt->greaterThan(now())) {
                    $signedAt = now()->subDays(3);
                }

                $payments = $this->paymentPlan($index);
            }

            $specs[] = [
                'title' => $title,
                'amount' => $this->pickAmount($currency->short_name, $index),
                'currency' => $currency,
                'type' => $types[$index % $types->count()],
                'contact' => $contacts[$index % $contacts->count()],
                'status' => $status,
                'created_at' => $createdAt,
                'signed_at' => $signedAt,
                'overdue' => $index % 2 === 0,
                'payments' => $payments,
            ];
        }

        return $specs;
    }

    /** @param  Collection<string, Currency>  $currencies */
    private function pickCurrency(Collection $currencies, int $index): Currency
    {
        $short = match (true) {
            $index % 11 === 5 && $currencies->has('RUB') => 'RUB',
            $index % 7 === 3 && $currencies->has('EUR') => 'EUR',
            $index % 5 === 1 && $currencies->has('USD') => 'USD',
            default => 'UZS',
        };

        return $currencies->get($short) ?? $currencies->get('UZS') ?? $currencies->first();
    }

    private function pickAmount(string $currencyShort, int $index): int
    {
        return match ($currencyShort) {
            'USD' => 4_000 + ($index % 6) * 9_000,
            'EUR' => 6_000 + ($index % 5) * 8_000,
            'RUB' => 250_000 + ($index % 4) * 600_000,
            default => 15_000_000 + ($index % 8) * 28_000_000,
        };
    }

    /** @return list<int> */
    private function paymentPlan(int $index): array
    {
        return match ($index % 5) {
            1 => [50],
            2 => [40, 60],
            3 => [30],
            4 => [100],
            default => [],
        };
    }
}
