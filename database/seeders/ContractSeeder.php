<?php

namespace Database\Seeders;

use App\Enums\ContractStatus;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\ContractTemplate;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\User;
use App\Services\Documents\ContractPlaceholderValues;
use App\Services\Documents\TemplateFiller;
use Illuminate\Database\Seeder;

class ContractSeeder extends Seeder
{
    public function run(): void
    {
        $manager = User::firstWhere('email', 'manager@test.uz');
        $legal = User::firstWhere('email', 'legal@test.uz');
        $accountant = User::firstWhere('email', 'accounting@test.uz');
        $director = User::firstWhere('email', 'mr.silverwind1998@gmail.com') ?? User::first();

        if (! $manager || ! $director) {
            $this->command?->warn('ContractSeeder skipped: TestUsersSeeder must run first.');

            return;
        }

        $uzs = Currency::firstWhere('short_name', 'UZS');
        $usd = Currency::firstWhere('short_name', 'USD');

        $contacts = Contact::query()->orderBy('id')->get();
        $templates = ContractTemplate::query()->where('status', true)->orderBy('sort')->get();

        if ($contacts->isEmpty() || $templates->isEmpty()) {
            $this->command?->warn('ContractSeeder skipped: contacts and templates must exist first.');

            return;
        }

        $filler = app(TemplateFiller::class);
        $values = app(ContractPlaceholderValues::class);

        $contracts = [
            [
                'title' => 'Аренда офисного помещения на 2026 год',
                'amount' => 180_000_000,
                'currency' => $uzs,
                'template' => $templates->first(fn ($t) => str_contains($t->name, 'аренды')) ?? $templates->first(),
                'contact' => $contacts->first(),
                'status' => Contract::STATUS_APPROVED,
                'created_at' => now()->subMonths(5)->startOfMonth()->addDays(8),
                'signed_at' => now()->subMonths(4)->startOfMonth()->addDays(12),
                'payments' => [40, 60],
            ],
            [
                'title' => 'Оказание услуг по организации туров',
                'amount' => 25_000,
                'currency' => $usd,
                'template' => $templates->first(fn ($t) => str_contains($t->name, 'услуг')) ?? $templates->first(),
                'contact' => $contacts->skip(1)->first() ?? $contacts->first(),
                'status' => Contract::STATUS_APPROVED,
                'created_at' => now()->subMonths(3)->startOfMonth()->addDays(5),
                'signed_at' => now()->subMonths(2)->startOfMonth()->addDays(10),
                'payments' => [30],
            ],
            [
                'title' => 'Услуги транспортной логистики',
                'amount' => 95_000_000,
                'currency' => $uzs,
                'template' => $templates->first(fn ($t) => str_contains($t->name, 'услуг')) ?? $templates->first(),
                'contact' => $contacts->skip(2)->first() ?? $contacts->first(),
                'status' => Contract::STATUS_IN_REVIEW,
                'created_at' => now()->subMonths(1)->startOfMonth()->addDays(14),
                'payments' => [],
            ],
            [
                'title' => 'Маркетинговое сопровождение тура',
                'amount' => 12_500_000,
                'currency' => $uzs,
                'template' => $templates->first(fn ($t) => str_contains($t->name, 'услуг')) ?? $templates->first(),
                'contact' => $contacts->first(),
                'status' => Contract::STATUS_DRAFT,
                'created_at' => now()->subDays(14),
                'payments' => [],
            ],
            [
                'title' => 'Договор с физлицом-гидом',
                'amount' => 8_000_000,
                'currency' => $uzs,
                'template' => $templates->first(fn ($t) => str_contains(strtolower($t->name), 'uz')) ?? $templates->first(),
                'contact' => $contacts->firstWhere('type', Contact::TYPE_INDIVIDUAL) ?? $contacts->last(),
                'status' => Contract::STATUS_REJECTED,
                'created_at' => now()->subMonths(4)->startOfMonth()->addDays(20),
                'payments' => [],
            ],
        ];

        foreach ($contracts as $index => $data) {
            if (! $data['currency'] || ! $data['template'] || ! $data['contact']) {
                continue;
            }

            $contract = Contract::firstOrCreate(
                ['title' => $data['title']],
                [
                    'number' => 'DEMO-'.now()->year.'-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'contract_template_id' => $data['template']->id,
                    'order_type_id' => $data['template']->order_type_id,
                    'contact_id' => $data['contact']->id,
                    'currency_id' => $data['currency']->id,
                    'responsible_id' => $manager->id,
                    'amount' => $data['amount'],
                    'status' => $data['status'],
                    'signed_at' => $data['signed_at'] ?? null,
                ]
            );

            if (! $contract->wasRecentlyCreated) {
                continue;
            }

            $contract->buildDocumentFromTemplate($filler, $values);

            Contract::query()->whereKey($contract->id)->update([
                'status' => $data['status']->value,
                'signed_at' => $data['signed_at'] ?? null,
                'created_at' => $data['created_at'] ?? $contract->created_at,
                'updated_at' => $data['signed_at'] ?? $data['created_at'] ?? $contract->updated_at,
            ]);
            $contract->refresh();

            $contract->approvers()->delete();

            $this->seedApprovers($contract, $manager, $legal, $accountant, $director, $data['status']);
            $this->seedPayments($contract, $accountant ?? $manager, $data['payments']);
        }
    }

    private function seedApprovers(
        Contract $contract,
        User $manager,
        ?User $legal,
        ?User $accountant,
        ?User $director,
        ContractStatus $status,
    ): void {
        // The director joins only as the final manual sign-off, so they appear
        // in the chain solely on contracts that reached full approval — never
        // in a draft / in-review / rejected chain.
        $chain = collect([$legal, $accountant])
            ->when($status === ContractStatus::Approved, fn ($c) => $c->push($director))
            ->filter()
            ->values();

        if ($chain->isEmpty()) {
            return;
        }

        $approvedComments = [
            'Согласовано с юридической стороны, замечаний нет.',
            'Финансовых возражений нет, сумма подтверждена.',
            'Утверждаю. Можно подписывать.',
        ];

        $order = 1;

        foreach ($chain as $index => $user) {
            $approverStatus = match (true) {
                $status === ContractStatus::Draft => ContractApprover::STATUS_QUEUED,
                $status === ContractStatus::Approved => ContractApprover::STATUS_APPROVED,
                $status === ContractStatus::InReview && $index === 0 => ContractApprover::STATUS_PENDING,
                $status === ContractStatus::InReview => ContractApprover::STATUS_QUEUED,
                $status === ContractStatus::Rejected && $index === 0 => ContractApprover::STATUS_APPROVED,
                $status === ContractStatus::Rejected && $index === 1 => ContractApprover::STATUS_REJECTED,
                $status === ContractStatus::Rejected => ContractApprover::STATUS_QUEUED,
                default => ContractApprover::STATUS_QUEUED,
            };

            $comment = match ($approverStatus) {
                ContractApprover::STATUS_REJECTED => 'Уточните, пожалуйста, сумму НДС в п. 3.2.',
                ContractApprover::STATUS_APPROVED => $approvedComments[$index] ?? $approvedComments[0],
                default => null,
            };

            ContractApprover::create([
                'contract_id' => $contract->id,
                'user_id' => $user->id,
                'order' => $order++,
                'status' => $approverStatus,
                'acted_at' => in_array(
                    $approverStatus,
                    [ContractApprover::STATUS_APPROVED, ContractApprover::STATUS_REJECTED],
                    true,
                ) ? now()->subDays(rand(1, 10)) : null,
                'due_at' => $approverStatus === ContractApprover::STATUS_PENDING
                    ? now()->subDays(2)
                    : null,
                'comment' => $comment,
            ]);
        }
    }

    /** @param  list<float>  $percentages */
    private function seedPayments(Contract $contract, User $accountant, array $percentages): void
    {
        foreach ($percentages as $index => $percent) {
            Payment::create([
                'contract_id' => $contract->id,
                'created_by' => $accountant->id,
                'percent' => $percent,
                'paid_at' => now()->subDays(15 - $index * 5),
                'screenshot' => 'uploads/images/payments/seeded/'.$contract->id.'-'.($index + 1).'.png',
            ]);
        }
    }
}
