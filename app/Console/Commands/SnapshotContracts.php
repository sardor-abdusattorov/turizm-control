<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Dumps every hand-entered contract — with its counterparty, bank accounts,
 * dossier attachments, payments and basis order — into a JSON file that
 * HandEnteredContractsSeeder replays after `migrate:fresh --seed`. File paths
 * are stored verbatim: the uploads/ tree on the server is never touched, so
 * the restored records point at the same files.
 *
 * Run BEFORE rebuilding the database:  php artisan contracts:snapshot
 */
class SnapshotContracts extends Command
{
    protected $signature = 'contracts:snapshot {--path=}';

    protected $description = 'Save all contracts (+contacts, attachments, payments, orders) to a JSON snapshot the seeder can replay';

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
        // the 2025 registry ones.
        $orders = Order::query()->orderBy('id')->get()
            ->map(fn (Order $order): array => [
                'number' => $order->number,
                'scope' => $order->scope->value,
                'title' => $order->title,
                'description' => $order->description,
                'issued_at' => $order->issued_at?->toDateString(),
                'file_path' => $order->file_path,
                'status' => (bool) $order->status,
            ]);

        $path = $this->option('path') ?: database_path('seeders/data/contracts-snapshot.json');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode(
            ['contracts' => $contracts, 'orders' => $orders],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));

        $this->info("Snapshot: {$contracts->count()} contracts, {$orders->count()} orders → {$path}");

        return self::SUCCESS;
    }
}
