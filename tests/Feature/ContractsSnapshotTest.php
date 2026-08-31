<?php

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
use App\Models\User;
use Database\Seeders\HandEnteredContractsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

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

        ->and($restored->contact?->inn)->toBe('301234567')
        ->and($restored->contact?->bankAccounts()->value('account_number'))->toBe('20208000900123456001')

        ->and($restored->attachments()->value('file_path'))->toBe('uploads/files/contract-attachments/2026/07/scan.pdf')
        ->and($restored->attachments()->value('original_name'))->toBe('Эркин шаклдаги хужжат А-2.pdf')
        ->and((float) $restored->payments()->value('percent'))->toBe(40.0)
        ->and($restored->payments()->first()?->screenshots)->toBe(['uploads/images/payments/2026/07/proof.png'])

        ->and($restored->approvers()->count())->toBe(0);
});

it('hands the basis order to the project on replay', function () {
    Storage::fake('local');

    $order = Order::factory()->create(['number' => '119-AF']);
    $project = Project::factory()->international()->create(['name' => 'ATM 25', 'order_id' => $order->id]);
    $currency = Currency::factory()->create(['short_name' => 'UZS', 'status' => true]);
    Contract::factory()->create(['number' => 'А-9', 'currency_id' => $currency->id, 'project_id' => $project->id]);

    $this->artisan('contracts:snapshot', ['--path' => $this->snapshotPath])->assertSuccessful();

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
    Payment::factory()->forContract($contract)->create(['percent' => 40, 'paid_at' => '2026-02-11']);

    $this->artisan('contracts:snapshot', ['--path' => $this->snapshotPath])->assertSuccessful();

    $this->seed(HandEnteredContractsSeeder::class);
    $this->seed(HandEnteredContractsSeeder::class);

    $restored = Contract::query()->firstWhere('number', 'B-1');

    expect(Contract::query()->where('number', 'B-1')->count())->toBe(1)
        ->and($restored->attachments()->count())->toBe(1)
        ->and($restored->payments()->count())->toBe(1)

        ->and((float) $restored->fresh()->paid_percent)->toBe(40.0);
});

it('the seeder is a silent no-op without a snapshot file', function () {
    $this->seed(HandEnteredContractsSeeder::class);

    expect(Contract::query()->count())->toBe(0);
});

it('carries the committee → PR centre basis link across a wipe', function () {
    Storage::fake('local');

    $committee = Order::factory()->committee()->create(['number' => '06-АФ']);
    $centre = Order::factory()->prCenter()->create([
        'number' => 'ПР-14',
        'basis_order_id' => $committee->id,
    ]);

    $this->artisan('contracts:snapshot', ['--path' => $this->snapshotPath])->assertSuccessful();

    Order::query()->delete();

    $this->seed(HandEnteredContractsSeeder::class);

    $restored = Order::query()->firstWhere('number', 'ПР-14');

    expect($restored)->not->toBeNull()
        ->and($restored->scope)->toBe(OrderScope::PrCenter)
        ->and($restored->basisOrder?->number)->toBe('06-АФ')

        ->and($restored->basis_order_id)->not->toBe($committee->id)
        ->and(Order::query()->firstWhere('number', '06-АФ')->basis_order_id)->toBeNull();
});

it('carries a direct project payment across a wipe', function () {
    Storage::fake('local');

    $currency = Currency::factory()->create(['short_name' => 'UZS', 'status' => true]);
    $project = Project::factory()->international()->create(['name' => 'ITB Berlin 2026']);
    $author = User::factory()->create(['email' => 'payer@test.uz', 'status' => User::STATUS_ACTIVE]);

    Payment::factory()->forProject($project)->create([
        'amount' => 12_500_000,
        'currency_id' => $currency->id,
        'purpose' => 'Аренда звукового оборудования',
        'paid_at' => '2026-03-04',
        'screenshots' => ['uploads/images/payments/2026/03/proof.png'],
        'created_by' => $author->id,
    ]);

    $this->artisan('contracts:snapshot', ['--path' => $this->snapshotPath])->assertSuccessful();

    Payment::query()->delete();

    $this->seed(HandEnteredContractsSeeder::class);

    $restored = Payment::query()->whereNull('contract_id')->first();

    expect($restored)->not->toBeNull()
        ->and($restored->project?->name)->toBe('ITB Berlin 2026')
        ->and((float) $restored->amount)->toBe(12_500_000.0)
        ->and($restored->currency?->short_name)->toBe('UZS')
        ->and($restored->purpose)->toBe('Аренда звукового оборудования')
        ->and($restored->paid_at?->format('Y-m-d'))->toBe('2026-03-04')
        ->and($restored->screenshots)->toBe(['uploads/images/payments/2026/03/proof.png'])
        ->and($restored->creator?->email)->toBe('payer@test.uz');
});

it('carries requisitions across a wipe, review state and all', function () {
    Storage::fake('local');

    $project = Project::factory()->international()->create(['name' => 'ATM 25']);
    $author = User::factory()->create(['email' => 'author@test.uz', 'status' => User::STATUS_ACTIVE]);
    $reviewer = User::factory()->create(['email' => 'supply@test.uz', 'status' => User::STATUS_ACTIVE]);

    Requisition::factory()->rejected([$reviewer])->create([
        'number' => 'ЗВ-2026-001',
        'title' => 'Канцелярия на III квартал',
        'description' => 'Бумага А4 — 20 пачек.',
        'project_id' => $project->id,
        'author_id' => $author->id,
    ]);

    $this->artisan('contracts:snapshot', ['--path' => $this->snapshotPath])->assertSuccessful();

    Requisition::query()->delete();

    $this->seed(HandEnteredContractsSeeder::class);

    $restored = Requisition::query()->firstWhere('number', 'ЗВ-2026-001');

    expect($restored)->not->toBeNull()
        ->and($restored->title)->toBe('Канцелярия на III квартал')
        ->and($restored->description)->toBe('Бумага А4 — 20 пачек.')
        ->and($restored->status)->toBe(RequisitionStatus::Rejected)
        ->and($restored->project?->name)->toBe('ATM 25')
        ->and($restored->author?->email)->toBe('author@test.uz')

        ->and($restored->approvals)->toHaveCount(1)
        ->and($restored->approvals->first()->user?->email)->toBe('supply@test.uz')
        ->and($restored->approvals->first()->status)->toBe(ApprovalStatus::Rejected)
        ->and($restored->approvals->first()->comment)->toBe('Уточните смету и приложите обоснование.')
        ->and($restored->approvals->first()->acted_at)->not->toBeNull();
});

it('never duplicates project payments or requisitions on a second replay', function () {
    Storage::fake('local');

    $currency = Currency::factory()->create(['short_name' => 'UZS', 'status' => true]);
    $project = Project::factory()->international()->create(['name' => 'ATM 25']);
    Payment::factory()->forProject($project)->create(['amount' => 500, 'currency_id' => $currency->id, 'paid_at' => '2026-01-05']);
    Requisition::factory()->withChain()->create(['number' => 'ЗВ-2026-009']);

    $this->artisan('contracts:snapshot', ['--path' => $this->snapshotPath])->assertSuccessful();

    $this->seed(HandEnteredContractsSeeder::class);
    $this->seed(HandEnteredContractsSeeder::class);

    expect(Payment::query()->whereNull('contract_id')->count())->toBe(1)
        ->and(Requisition::query()->where('number', 'ЗВ-2026-009')->count())->toBe(1);
});

it('snapshots the live data before project:init drops it', function () {
    Storage::fake('local');

    $currency = Currency::factory()->create(['short_name' => 'UZS', 'status' => true]);
    Contract::factory()->create(['number' => 'LIVE-1', 'currency_id' => $currency->id]);

    $realPath = database_path('seeders/data/contracts-snapshot.json');
    $backup = File::exists($realPath) ? File::get($realPath) : null;

    try {
        $this->artisan('contracts:snapshot', ['--force' => true])->assertSuccessful();

        expect(File::get($realPath))->toContain('LIVE-1');
    } finally {
        $backup === null ? File::delete($realPath) : File::put($realPath, $backup);
    }
});

it('refuses to overwrite a snapshot with fewer records than it already holds', function () {
    Contract::factory()->count(3)->create();

    $this->artisan('contracts:snapshot', ['--path' => $this->snapshotPath])->assertSuccessful();

    Contract::query()->delete();

    $this->artisan('contracts:snapshot', ['--path' => $this->snapshotPath])->assertFailed();

    $kept = json_decode(File::get($this->snapshotPath), true);

    expect($kept['contracts'])->toHaveCount(3);
});

it('overwrites a shrinking snapshot when forced', function () {
    Contract::factory()->count(3)->create();

    $this->artisan('contracts:snapshot', ['--path' => $this->snapshotPath])->assertSuccessful();

    Contract::query()->delete();

    $this->artisan('contracts:snapshot', ['--path' => $this->snapshotPath, '--force' => true])->assertSuccessful();

    expect(json_decode(File::get($this->snapshotPath), true)['contracts'])->toBeEmpty();
});
