<?php

namespace Database\Seeders;

use App\Enums\ApprovalStatus;
use App\Enums\OrderScope;
use App\Enums\RequisitionStatus;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Requisition;
use App\Models\Sponsor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class HandEnteredContractsSeeder extends Seeder
{
    public static ?string $path = null;

    public function run(): void
    {
        $path = static::$path ?? database_path('seeders/data/contracts-snapshot.json');

        if (! File::exists($path)) {
            return;
        }

        $snapshot = json_decode(File::get($path), true) ?: [];

        foreach ($snapshot['orders'] ?? [] as $data) {
            Order::updateOrCreate(
                ['number' => $data['number']],
                [
                    'scope' => $this->scopeFrom($data['scope'] ?? null),
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'issued_at' => $data['issued_at'] ?? null,
                    'file_path' => $data['file_path'] ?? null,
                    'status' => $data['status'] ?? true,
                    'created_by' => $this->fallbackUserId(),
                ],
            );
        }

        $this->relinkOrderBases($snapshot['orders'] ?? []);

        foreach ($snapshot['contracts'] ?? [] as $data) {
            $contract = Contract::updateOrCreate(
                ['number' => $data['number']],
                [
                    'title' => $data['title'],
                    'amount' => $data['amount'],
                    'currency_id' => Currency::query()->firstWhere('short_name', $data['currency'])?->id,
                    'contract_type_id' => ContractType::query()->firstWhere('title->ru', $data['contract_type'])?->id,
                    'project_id' => $data['project'] ? Project::query()->firstWhere('name', $data['project'])?->id : null,
                    'contact_id' => $this->restoreContact($data['contact'] ?? null),
                    'sponsor_id' => $data['sponsor'] ? Sponsor::query()->firstWhere('name', $data['sponsor'])?->id : null,
                    'responsible_id' => $this->userId($data['responsible_email'] ?? null),
                ],
            );

            $contract->forceFill([
                'status' => $data['status'],
                'signed_at' => $data['signed_at'],
            ])->saveQuietly();

            if (($data['order_number'] ?? null) && $contract->project_id) {
                $orderId = Order::query()->firstWhere('number', $data['order_number'])?->id;

                if ($orderId) {
                    Project::query()
                        ->whereKey($contract->project_id)
                        ->whereNull('order_id')
                        ->update(['order_id' => $orderId]);
                }
            }

            foreach ($data['attachments'] ?? [] as $sort => $attachment) {
                $contract->attachments()->firstOrCreate(
                    ['file_path' => $attachment['file_path']],
                    [
                        'original_name' => $attachment['original_name'],
                        'type' => $attachment['type'] ?? null,
                        'size' => $attachment['size'] ?? 0,
                        'sort' => $attachment['sort'] ?? $sort + 1,
                        'uploaded_by' => $contract->responsible_id,
                    ],
                );
            }

            foreach ($data['payments'] ?? [] as $payment) {
                $alreadyFiled = $contract->payments()
                    ->where('percent', $payment['percent'])
                    ->whereDate('paid_at', $payment['paid_at'])
                    ->exists();

                if ($alreadyFiled) {
                    continue;
                }

                $contract->payments()->create([
                    'percent' => $payment['percent'],
                    'paid_at' => $payment['paid_at'],
                    'screenshots' => $payment['screenshots'] ?? [],
                    'created_by' => $this->userId($payment['created_by_email'] ?? null),
                ]);
            }
        }

        $this->restoreProjectPayments($snapshot['project_payments'] ?? []);
        $this->restoreRequisitions($snapshot['requisitions'] ?? []);
    }

    /** @param  array<int, array<string, mixed>>  $orders */
    protected function relinkOrderBases(array $orders): void
    {
        foreach ($orders as $data) {
            if (blank($data['basis_number'] ?? null)) {
                continue;
            }

            $order = Order::query()->firstWhere('number', $data['number']);
            $basisId = Order::query()->where('number', $data['basis_number'])->value('id');

            if ($order && $basisId && $order->getKey() !== $basisId) {
                $order->forceFill(['basis_order_id' => $basisId])->saveQuietly();
            }
        }
    }

    /** @param  array<int, array<string, mixed>>  $payments */
    protected function restoreProjectPayments(array $payments): void
    {
        foreach ($payments as $data) {
            $projectId = filled($data['project'] ?? null)
                ? Project::query()->where('name', $data['project'])->value('id')
                : null;

            if (! $projectId) {
                continue;
            }

            $alreadyFiled = Payment::query()
                ->whereNull('contract_id')
                ->where('project_id', $projectId)
                ->where('amount', $data['amount'])
                ->whereDate('paid_at', $data['paid_at'])
                ->exists();

            if ($alreadyFiled) {
                continue;
            }

            Payment::create([
                'contract_id' => null,
                'project_id' => $projectId,
                'amount' => $data['amount'],
                'paid_at' => $data['paid_at'],
                'currency_id' => Currency::query()->where('short_name', $data['currency'])->value('id'),
                'purpose' => $data['purpose'] ?? null,
                'screenshots' => $data['screenshots'] ?? [],
                'created_by' => $this->userId($data['created_by_email'] ?? null),
            ]);
        }
    }

    /** @param  array<int, array<string, mixed>>  $requisitions */
    protected function restoreRequisitions(array $requisitions): void
    {
        foreach ($requisitions as $data) {
            $requisition = Requisition::updateOrCreate(
                ['number' => $data['number']],
                [
                    'title' => $data['title'],
                    'description' => $data['description'] ?? '',
                    'project_id' => filled($data['project'] ?? null)
                        ? Project::query()->where('name', $data['project'])->value('id')
                        : null,
                    'author_id' => $this->userId($data['author_email'] ?? null),
                ],
            );

            $requisition->forceFill([
                'status' => $data['status'] ?? RequisitionStatus::Draft->value,
                'submitted_at' => $data['submitted_at'] ?? null,
            ])->saveQuietly();

            foreach ($data['approvals'] ?? [] as $approval) {
                $userId = filled($approval['user_email'] ?? null)
                    ? User::query()->where('email', $approval['user_email'])->value('id')
                    : null;

                if (! $userId) {
                    continue;
                }

                $requisition->approvals()->firstOrCreate(
                    [
                        'user_id' => $userId,
                        'round' => $approval['round'] ?? 1,
                    ],
                    [
                        'order' => $approval['order'] ?? 1,
                        'status' => $approval['status'] ?? ApprovalStatus::Queued->value,
                        'original_status' => $approval['original_status'] ?? null,
                        'comment' => $approval['comment'] ?? null,
                        'acted_at' => $approval['acted_at'] ?? null,
                        'due_at' => $approval['due_at'] ?? null,
                    ],
                );
            }

            $requisition->unsetRelation('approvals');
        }
    }

    /** @param  array<string, mixed>|null  $data */
    protected function restoreContact(?array $data): ?int
    {
        if (! $data) {
            return null;
        }

        $key = filled($data['inn'] ?? null)
            ? ['inn' => $data['inn']]
            : ['name->ru' => $data['name']['ru'] ?? reset($data['name'])];

        $contact = Contact::updateOrCreate($key, [
            'name' => $data['name'],
            'type' => $data['type'] ?? Contact::TYPE_LEGAL,
            'legal_form' => $data['legal_form'] ?? null,
            'inn' => $data['inn'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? [],
            'director_name' => $data['director_name'] ?? null,
            'status' => true,
        ]);

        foreach ($data['bank_accounts'] ?? [] as $sort => $account) {
            $contact->bankAccounts()->firstOrCreate(
                ['account_number' => $account['account_number']],
                [
                    'currency_id' => Currency::query()->firstWhere('short_name', $account['currency'])?->id,
                    'bank_name' => $account['bank_name'] ?? null,
                    'bank_address' => $account['bank_address'] ?? null,
                    'mfo' => $account['mfo'] ?? null,
                    'swift' => $account['swift'] ?? null,
                    'sort' => $sort + 1,
                ],
            );
        }

        return $contact->id;
    }

    protected function userId(?string $email): int
    {
        return ($email ? User::query()->firstWhere('email', $email)?->id : null)
            ?? $this->fallbackUserId();
    }

    protected function fallbackUserId(): int
    {
        return User::query()->orderBy('id')->value('id');
    }

    protected function scopeFrom(?string $scope): string
    {
        return match ($scope) {
            'internal', OrderScope::PrCenter->value => OrderScope::PrCenter->value,
            default => OrderScope::Committee->value,
        };
    }
}
