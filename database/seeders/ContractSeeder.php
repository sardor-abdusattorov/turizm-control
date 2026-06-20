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
                'signed_at' => now()->subDays(20),
                'payments' => [40, 60],
            ],
            [
                'title' => 'Оказание услуг по организации туров',
                'amount' => 25_000,
                'currency' => $usd,
                'template' => $templates->first(fn ($t) => str_contains($t->name, 'услуг')) ?? $templates->first(),
                'contact' => $contacts->skip(1)->first() ?? $contacts->first(),
                'status' => Contract::STATUS_APPROVED,
                'signed_at' => now()->subDays(7),
                'payments' => [30],
            ],
            [
                'title' => 'Услуги транспортной логистики',
                'amount' => 95_000_000,
                'currency' => $uzs,
                'template' => $templates->first(fn ($t) => str_contains($t->name, 'услуг')) ?? $templates->first(),
                'contact' => $contacts->skip(2)->first() ?? $contacts->first(),
                'status' => Contract::STATUS_IN_REVIEW,
                'payments' => [],
            ],
            [
                'title' => 'Маркетинговое сопровождение тура',
                'amount' => 12_500_000,
                'currency' => $uzs,
                'template' => $templates->first(fn ($t) => str_contains($t->name, 'услуг')) ?? $templates->first(),
                'contact' => $contacts->first(),
                'status' => Contract::STATUS_DRAFT,
                'payments' => [],
            ],
            [
                'title' => 'Договор с физлицом-гидом',
                'amount' => 8_000_000,
                'currency' => $uzs,
                'template' => $templates->first(fn ($t) => str_contains(strtolower($t->name), 'uz')) ?? $templates->first(),
                'contact' => $contacts->firstWhere('type', Contact::TYPE_INDIVIDUAL) ?? $contacts->last(),
                'status' => Contract::STATUS_REJECTED,
                'payments' => [],
            ],
        ];

        foreach ($contracts as $data) {
            if (! $data['currency'] || ! $data['template'] || ! $data['contact']) {
                continue;
            }

            $contract = Contract::firstOrCreate(
                ['title' => $data['title']],
                [
                    'contract_template_id' => $data['template']->id,
                    'order_type_id' => $data['template']->order_type_id,
                    'contact_id' => $data['contact']->id,
                    'currency_id' => $data['currency']->id,
                    'responsible_id' => $manager->id,
                    'language' => $data['template']->language ?? 'ru',
                    'amount' => $data['amount'],
                    'status' => $data['status'],
                    'signed_at' => $data['signed_at'] ?? null,
                ]
            );

            if (! $contract->wasRecentlyCreated) {
                continue;
            }

            $contract->buildDocumentFromTemplate($filler, $values);

            // buildDocumentFromTemplate writes document_file via update(), which
            // trips Contract::maybeInvalidateOnEdit and drops everything back to
            // draft. Re-apply the seeded status straight through the query
            // builder so the observer doesn't touch it again.
            Contract::query()->whereKey($contract->id)->update([
                'status' => $data['status']->value,
                'signed_at' => $data['signed_at'] ?? null,
            ]);
            $contract->refresh();

            $this->seedApprovers($contract, $manager, $legal, $accountant, $director, $data['status']);
            $this->seedPayments($contract, $accountant ?? $manager, $data['payments']);
        }
    }

    /**
     * Build the approval chain for the seeded contract to match its stage:
     * - draft: queued chain, nothing decided yet
     * - in_review: legal is currently pending, accountant queued, director queued
     * - approved: every step approved
     * - rejected: legal approved, accountant rejected
     */
    private function seedApprovers(
        Contract $contract,
        User $manager,
        ?User $legal,
        ?User $accountant,
        ?User $director,
        ContractStatus $status,
    ): void {
        $chain = collect([$legal, $accountant, $director])->filter()->values();

        if ($chain->isEmpty()) {
            return;
        }

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
                'comment' => $approverStatus === ContractApprover::STATUS_REJECTED
                    ? 'Уточните, пожалуйста, сумму НДС.'
                    : null,
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
                // Real screenshots would live on disk; the seeded record just
                // points to a deterministic placeholder path so the row is
                // valid. Replace with an actual file when QA needs it.
                'screenshot' => 'payments/seeded/'.$contract->id.'-'.($index + 1).'.png',
            ]);
        }
    }
}
