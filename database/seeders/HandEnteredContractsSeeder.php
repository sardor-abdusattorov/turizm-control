<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Project;
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
                    'scope' => $data['scope'] ?? 'external',
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'issued_at' => $data['issued_at'] ?? null,
                    'file_path' => $data['file_path'] ?? null,
                    'status' => $data['status'] ?? true,
                    'created_by' => $this->fallbackUserId(),
                ],
            );
        }

        foreach ($snapshot['contracts'] ?? [] as $data) {
            $contract = Contract::updateOrCreate(
                ['number' => $data['number']],
                [
                    'title' => $data['title'],
                    'amount' => $data['amount'],
                    'currency_id' => Currency::query()->firstWhere('short_name', $data['currency'])?->id,
                    'contract_type_id' => ContractType::query()->firstWhere('title->ru', $data['contract_type'])?->id,
                    'project_id' => $data['project'] ? Project::query()->firstWhere('name', $data['project'])?->id : null,
                    'order_id' => $data['order_number'] ? Order::query()->firstWhere('number', $data['order_number'])?->id : null,
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
                $contract->payments()->firstOrCreate(
                    [
                        'percent' => $payment['percent'],
                        'paid_at' => $payment['paid_at'],
                    ],
                    [
                        'screenshots' => $payment['screenshots'] ?? [],
                        'created_by' => $this->userId($payment['created_by_email'] ?? null),
                    ],
                );
            }
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
}
