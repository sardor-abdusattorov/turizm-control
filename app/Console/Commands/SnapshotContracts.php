<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Requisition;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Dumps every hand-entered record — contracts with their counterparties, bank
 * accounts, dossier attachments and payments; buyruqs with the basis link
 * between them; payments filed straight against a project; and requisitions —
 * into a JSON file that HandEnteredContractsSeeder replays after
 * `migrate:fresh --seed`. File paths are stored verbatim: the uploads/ tree on
 * the server is never touched, so the restored records point at the same files.
 *
 * Records are named by their number, not their id: a rebuild reassigns ids,
 * so every cross-reference in here is by the natural key instead.
 *
 * Run BEFORE rebuilding the database:  php artisan contracts:snapshot
 */
class SnapshotContracts extends Command
{
    protected $signature = 'contracts:snapshot {--path=}';

    protected $description = 'Save all hand-entered records (contracts, orders, payments, requisitions) to a JSON snapshot the seeder can replay';

    public function handle(): int
    {
        $contracts = Contract::query()
            ->with([
                'contact.bankAccounts.currency', 'sponsor', 'contractType', 'currency',
                'project.order', 'responsible', 'attachments', 'payments.creator',
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (Contract $contract): array => [
                'number' => $contract->number,
                'title' => $contract->title,
                'amount' => (string) $contract->amount,
                'currency' => $contract->currency?->short_name,
                'status' => $contract->status->value,
                'signed_at' => $contract->signed_at?->toDateString(),
                'contract_type' => $contract->contractType?->getTranslation('title', 'ru'),
                'project' => $contract->project?->name,
                'order_number' => $contract->project?->order?->number,
                'responsible_email' => $contract->responsible?->email,
                'contact' => $contract->contact ? [
                    'name' => $contract->contact->getTranslations('name'),
                    'inn' => $contract->contact->inn,
                    'type' => $contract->contact->type,
                    'legal_form' => $contract->contact->legal_form,
                    'phone' => $contract->contact->phone,
                    'email' => $contract->contact->email,
                    'address' => $contract->contact->getTranslations('address'),
                    'director_name' => $contract->contact->director_name,
                    'bank_accounts' => $contract->contact->bankAccounts
                        ->map(fn ($account): array => [
                            'currency' => $account->currency?->short_name,
                            'account_number' => $account->account_number,
                            'bank_name' => $account->bank_name,
                            'bank_address' => $account->bank_address,
                            'mfo' => $account->mfo,
                            'swift' => $account->swift,
                        ])->all(),
                ] : null,
                'sponsor' => $contract->sponsor?->name,
                'attachments' => $contract->attachments
                    ->map(fn ($attachment): array => [
                        'file_path' => $attachment->file_path,
                        'original_name' => $attachment->original_name,
                        'type' => $attachment->type,
                        'size' => $attachment->size,
                        'sort' => $attachment->sort,
                    ])->all(),
                'payments' => $contract->payments
                    ->map(fn ($payment): array => [
                        'percent' => (string) $payment->percent,
                        'paid_at' => $payment->paid_at?->toDateString(),
                        'screenshots' => $payment->screenshots ?? [],
                        'created_by_email' => $payment->creator?->email,
                    ])->all(),
            ]);

        // Orders entered by hand ride along — the dossier seeder only knows
        // the 2025 registry ones. The basis is named by number: ids are
        // reassigned on a rebuild, so the link has to survive by natural key.
        $orders = Order::query()->with('basisOrder')->orderBy('id')->get()
            ->map(fn (Order $order): array => [
                'number' => $order->number,
                'scope' => $order->scope->value,
                'basis_number' => $order->basisOrder?->number,
                'title' => $order->title,
                'description' => $order->description,
                'issued_at' => $order->issued_at?->toDateString(),
                'file_path' => $order->file_path,
                'status' => (bool) $order->status,
            ]);

        // Payments filed straight against a project hang off no contract, so
        // the nested loop above never sees them — they need their own list.
        $projectPayments = Payment::query()
            ->whereNull('contract_id')
            ->with(['project', 'currency', 'creator'])
            ->orderBy('id')
            ->get()
            ->map(fn (Payment $payment): array => [
                'project' => $payment->project?->name,
                'amount' => (string) $payment->amount,
                'currency' => $payment->currency?->short_name,
                'purpose' => $payment->purpose,
                'paid_at' => $payment->paid_at?->toDateString(),
                'screenshots' => $payment->screenshots ?? [],
                'created_by_email' => $payment->creator?->email,
            ]);

        $requisitions = Requisition::query()
            ->with(['project', 'author', 'reviewer'])
            ->orderBy('id')
            ->get()
            ->map(fn (Requisition $requisition): array => [
                'number' => $requisition->number,
                'title' => $requisition->title,
                'description' => $requisition->description,
                'project' => $requisition->project?->name,
                'author_email' => $requisition->author?->email,
                'reviewer_email' => $requisition->reviewer?->email,
                'status' => $requisition->status->value,
                'submitted_at' => $requisition->submitted_at?->toDateTimeString(),
                'due_at' => $requisition->due_at?->toDateTimeString(),
                'reviewed_at' => $requisition->reviewed_at?->toDateTimeString(),
                'review_comment' => $requisition->review_comment,
            ]);

        $path = $this->option('path') ?: database_path('seeders/data/contracts-snapshot.json');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode(
            [
                'contracts' => $contracts,
                'orders' => $orders,
                'project_payments' => $projectPayments,
                'requisitions' => $requisitions,
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));

        $this->info(sprintf(
            'Snapshot: %d contracts, %d orders, %d project payments, %d requisitions → %s',
            $contracts->count(),
            $orders->count(),
            $projectPayments->count(),
            $requisitions->count(),
            $path,
        ));

        return self::SUCCESS;
    }
}
