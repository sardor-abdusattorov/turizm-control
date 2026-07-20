<?php

use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\HandEnteredContractsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// Tests snapshot into a scratch file — the REAL committed snapshot at
// database/seeders/data/ must survive test runs untouched.
beforeEach(function () {
    $this->snapshotPath = storage_path('framework/testing/contracts-snapshot-test.json');
    HandEnteredContractsSeeder::$path = $this->snapshotPath;
});

afterEach(function () {
    File::delete($this->snapshotPath);
    HandEnteredContractsSeeder::$path = null;
});

it('replays a snapshot after a wipe: contracts, contacts, attachments and payments come back verbatim', function () {
    Storage::fake('local');

    $currency = Currency::factory()->create(['short_name' => 'UZS', 'status' => true]);
    $type = ContractType::factory()->income()->create(['title' => ['ru' => 'Оказание услуг', 'uz' => 'X', 'en' => 'S']]);
    $project = Project::factory()->international()->create(['name' => 'ATM 25']);
    $author = User::factory()->create(['email' => 'author@test.uz', 'status' => User::STATUS_ACTIVE]);

    $contact = Contact::factory()->create([
        'name' => ['ru' => 'ООО «ZAMIN TRAVEL»', 'uz' => 'ZAMIN', 'en' => 'ZAMIN'],
        'inn' => '301234567',
        'status' => true,
    ]);
    $contact->bankAccounts()->create([
        'currency_id' => $currency->id,
        'account_number' => '20208000900123456001',
        'bank_name' => 'Kapitalbank',
        'mfo' => '00974',
        'sort' => 1,
    ]);

    $contract = Contract::factory()->create([
        'number' => 'А-2',
        'title' => 'Оказание услуг по организации единого национального стенда',
        'amount' => 10000000,
        'currency_id' => $currency->id,
        'contract_type_id' => $type->id,
        'project_id' => $project->id,
        'contact_id' => $contact->id,
        'responsible_id' => $author->id,
    ]);
    $contract->forceFill(['status' => Contract::STATUS_APPROVED, 'signed_at' => '2025-04-20'])->saveQuietly();

    $contract->attachments()->create([
        'file_path' => 'uploads/files/contract-attachments/2026/07/scan.pdf',
        'original_name' => 'Эркин шаклдаги хужжат А-2.pdf',
        'size' => 2942558,
        'sort' => 1,
        'uploaded_by' => $author->id,
    ]);
    Payment::factory()->forContract($contract)->create([
        'percent' => 40,
        'paid_at' => '2025-05-01',
        'screenshots' => ['uploads/images/payments/2026/07/proof.png'],
        'created_by' => $author->id,
    ]);

    $this->artisan('contracts:snapshot', ['--path' => $this->snapshotPath])->assertSuccessful();

    // The rebuild: contract-side data vanishes, reference data survives
    // (currencies / types / projects / users are re-seeded on fresh).
    Payment::query()->delete();
    $contract->attachments()->delete();
    Contract::query()->delete();
    $contact->bankAccounts()->delete();
    $contact->delete();

    $this->seed(HandEnteredContractsSeeder::class);

    $restored = Contract::query()->firstWhere('number', 'А-2');

    expect($restored)->not->toBeNull()
        ->and($restored->title)->toBe('Оказание услуг по организации единого национального стенда')
        ->and((float) $restored->amount)->toBe(10000000.0)
        ->and($restored->status)->toBe(Contract::STATUS_APPROVED)
        ->and($restored->signed_at?->format('Y-m-d'))->toBe('2025-04-20')
        ->and($restored->contractType?->id)->toBe($type->id)
        ->and($restored->project?->id)->toBe($project->id)
        ->and($restored->responsible?->email)->toBe('author@test.uz')
        // The counterparty is recreated by INN with its bank account intact.
        ->and($restored->contact?->inn)->toBe('301234567')
        ->and($restored->contact?->bankAccounts()->value('account_number'))->toBe('20208000900123456001')
        // The dossier points at the SAME path — files on disk are untouched.
        ->and($restored->attachments()->value('file_path'))->toBe('uploads/files/contract-attachments/2026/07/scan.pdf')
        ->and($restored->attachments()->value('original_name'))->toBe('Эркин шаклдаги хужжат А-2.pdf')
        ->and((float) $restored->payments()->value('percent'))->toBe(40.0)
        ->and($restored->payments()->first()?->screenshots)->toBe(['uploads/images/payments/2026/07/proof.png'])
        // No approval chain is conjured for a replayed contract.
        ->and($restored->approvers()->count())->toBe(0);
});

it('hands the basis order to the project on replay', function () {
    Storage::fake('local');

    $order = Order::factory()->create(['number' => '119-AF']);
    $project = Project::factory()->international()->create(['name' => 'ATM 25', 'order_id' => $order->id]);
    $currency = Currency::factory()->create(['short_name' => 'UZS', 'status' => true]);
    Contract::factory()->create(['number' => 'А-9', 'currency_id' => $currency->id, 'project_id' => $project->id]);

    $this->artisan('contracts:snapshot', ['--path' => $this->snapshotPath])->assertSuccessful();

    // The rebuild wipes the link; the replayed contract carries it back.
    $project->forceFill(['order_id' => null])->saveQuietly();
    Contract::query()->delete();

    $this->seed(HandEnteredContractsSeeder::class);

    expect($project->fresh()->order?->number)->toBe('119-AF');
});

it('replaying the same snapshot twice never duplicates anything', function () {
    Storage::fake('local');

    $currency = Currency::factory()->create(['short_name' => 'UZS', 'status' => true]);
    $contract = Contract::factory()->create(['number' => 'B-1', 'currency_id' => $currency->id]);
    $contract->attachments()->create([
        'file_path' => 'uploads/files/contract-attachments/b1.pdf',
        'original_name' => 'b1.pdf', 'size' => 1, 'sort' => 1,
    ]);

    $this->artisan('contracts:snapshot', ['--path' => $this->snapshotPath])->assertSuccessful();

    $this->seed(HandEnteredContractsSeeder::class);
    $this->seed(HandEnteredContractsSeeder::class);

    expect(Contract::query()->where('number', 'B-1')->count())->toBe(1)
        ->and(Contract::query()->firstWhere('number', 'B-1')->attachments()->count())->toBe(1);
});

it('the seeder is a silent no-op without a snapshot file', function () {
    $this->seed(HandEnteredContractsSeeder::class);

    expect(Contract::query()->count())->toBe(0);
});
