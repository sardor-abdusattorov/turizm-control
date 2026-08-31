<?php

namespace Database\Seeders;

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

/**
 * Replays the JSON written by `php artisan contracts:snapshot`: contracts,
 * their counterparties (with bank accounts), dossier attachments, payments
 * and hand-entered orders survive a `migrate:fresh --seed` exactly as they
 * were. File paths are relinked verbatim — the uploads/ tree on disk is
 * expected to be untouched by the rebuild.
 *
 * Silently does nothing when no snapshot file exists.
 */
class HandEnteredContractsSeeder extends Seeder
{
    /** Overridable so tests replay a scratch file, never the real snapshot. */
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

            // The workflow already happened in a previous life — file the
            // status verbatim, quietly, with no chains and no notifications.
            $contract->forceFill([
                'status' => $data['status'],
                'signed_at' => $data['signed_at'],
            ])->saveQuietly();

            // The basis buyruq lives on the project now; the first contract
            // naming one hands it over, the rest confirm it.
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
                // whereDate, not a plain where: paid_at is stored as a full
                // timestamp, so matching it against the snapshot's 'Y-m-d'
                // never hits and a second replay would file the payment twice.
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

    /**
     * A rebuild reassigns ids, so the snapshot names a buyruq's basis by its
     * number — and the link can only be tied once every order exists.
     *
     * @param  array<int, array<string, mixed>>  $orders
     */
    protected function relinkOrderBases(array $orders): void
    {
        foreach ($orders as $data) {
            if (blank($data['basis_number'] ?? null)) {
                continue;
            }

            $order = Order::query()->firstWhere('number', $data['number']);
            $basisId = Order::query()->where('number', $data['basis_number'])->value('id');

            // Never let a snapshot point an order at itself.
            if ($order && $basisId && $order->getKey() !== $basisId) {
                $order->forceFill(['basis_order_id' => $basisId])->saveQuietly();
            }
        }
    }

    /**
     * Project spending filed without a contract — it hangs off no contract, so
     * it is restored on its own rather than inside the contract loop.
     *
     * @param  array<int, array<string, mixed>>  $payments
     */
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

    /**
     * @param  array<int, array<string, mixed>>  $requisitions
     */
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
                    'reviewer_id' => filled($data['reviewer_email'] ?? null)
                        ? User::query()->where('email', $data['reviewer_email'])->value('id')
                        : null,
                ],
            );

            // The review already happened in a previous life: file its state
            // verbatim rather than replaying the workflow.
            $requisition->forceFill([
                'status' => $data['status'] ?? RequisitionStatus::Draft->value,
                'submitted_at' => $data['submitted_at'] ?? null,
                'due_at' => $data['due_at'] ?? null,
                'reviewed_at' => $data['reviewed_at'] ?? null,
                'review_comment' => $data['review_comment'] ?? null,
            ])->saveQuietly();
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

    /**
     * Snapshots taken before the buyruq registries were renamed still say
     * internal / external — replay them as PR-centre / committee rather than
     * refusing to restore hand-entered data.
     */
    protected function scopeFrom(?string $scope): string
    {
        return match ($scope) {
            'internal', OrderScope::PrCenter->value => OrderScope::PrCenter->value,
            default => OrderScope::Committee->value,
        };
    }
}
