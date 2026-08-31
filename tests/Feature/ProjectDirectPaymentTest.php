<?php

use App\Enums\PaymentSubject;
use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function paymentAuthorActing(array $extra = []): User
{
    $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);

    foreach ([...['view_any_payment', 'view_payment', 'create_payment'], ...$extra] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }

    actingAs($user->fresh());

    return $user;
}

it('records a payment straight against a project, with no contract', function () {
    Storage::fake('local');
    paymentAuthorActing();

    $project = Project::factory()->create();
    $currency = Currency::factory()->create(['status' => true, 'short_name' => 'UZS']);

    Livewire::test(CreatePayment::class)
        ->fillForm([
            'subject' => PaymentSubject::Project->value,
            'project_id' => $project->id,
            'currency_id' => $currency->id,
            'amount' => 12_500_000,
            'purpose' => 'Аренда звукового оборудования',
            'paid_at' => now()->subDay()->format('Y-m-d'),
            'screenshots' => [UploadedFile::fake()->create('чек.pdf', 20, 'application/pdf')],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $payment = Payment::firstWhere('project_id', $project->id);

    expect($payment)->not->toBeNull()
        ->and($payment->contract_id)->toBeNull()
        ->and($payment->percent)->toBeNull()
        ->and((float) $payment->amount)->toBe(12_500_000.0)
        ->and($payment->currency_id)->toBe($currency->id)
        ->and($payment->purpose)->toBe('Аренда звукового оборудования')
        ->and($payment->isDirect())->toBeTrue();
});

it('demands a project, a sum and a currency for a direct payment', function () {
    Storage::fake('local');
    paymentAuthorActing();

    Livewire::test(CreatePayment::class)
        ->fillForm([
            'subject' => PaymentSubject::Project->value,
            'paid_at' => now()->format('Y-m-d'),
        ])
        ->call('create')
        ->assertHasFormErrors([
            'project_id' => 'required',
            'amount' => 'required',
            'currency_id' => 'required',
        ]);
});

it('still demands a contract and a percent for a contract payment', function () {
    Storage::fake('local');
    paymentAuthorActing();

    Livewire::test(CreatePayment::class)
        ->fillForm([
            'subject' => PaymentSubject::Contract->value,
            'paid_at' => now()->format('Y-m-d'),
        ])
        ->call('create')
        ->assertHasFormErrors([
            'contract_id' => 'required',
            'percent' => 'required',
        ]);
});

it('hides the contract fields once the project subject is picked', function () {
    Storage::fake('local');
    paymentAuthorActing();

    Livewire::test(CreatePayment::class)
        ->assertFormFieldVisible('contract_id')
        ->assertFormFieldVisible('percent')
        ->assertFormFieldHidden('project_id')
        ->assertFormFieldHidden('amount')
        ->fillForm(['subject' => PaymentSubject::Project->value])
        ->assertFormFieldHidden('contract_id')
        ->assertFormFieldHidden('percent')
        ->assertFormFieldVisible('project_id')
        ->assertFormFieldVisible('amount')
        ->assertFormFieldVisible('purpose');
});

it('clears the contract when the subject switches to a project', function () {
    Storage::fake('local');
    paymentAuthorActing();

    $contract = Contract::factory()->approved()->create();

    Livewire::test(CreatePayment::class)
        ->fillForm(['subject' => PaymentSubject::Contract->value, 'contract_id' => $contract->id, 'percent' => 30])
        ->fillForm(['subject' => PaymentSubject::Project->value])
        ->assertFormSet(['contract_id' => null, 'percent' => null]);
});

it('leaves contract payment progress untouched by a direct project payment', function () {
    $contract = Contract::factory()->approved()->create();
    Payment::factory()->forContract($contract)->percent(40)->create();

    $project = Project::factory()->create();
    Payment::factory()->forProject($project)->create();

    expect((float) $contract->fresh()->paid_percent)->toBe(40.0);
});

it('shows both kinds side by side in the registry', function () {
    $overseer = paymentAuthorActing(['view_all_contracts']);

    $contractPayment = Payment::factory()->create();
    $projectPayment = Payment::factory()->forProject()->create();

    Livewire::test(ListPayments::class)
        ->assertCanSeeTableRecords([$contractPayment, $projectPayment]);
});

it('keeps a direct payment with whoever filed it', function () {
    $author = paymentAuthorActing();
    $mine = Payment::factory()->forProject()->create(['created_by' => $author->id]);
    $theirs = Payment::factory()->forProject()->create();

    Livewire::test(ListPayments::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('labels each kind by what it settles', function () {
    $currency = Currency::factory()->create(['short_name' => 'USD']);
    $project = Project::factory()->create(['name' => 'ITB Berlin 2026']);

    $direct = Payment::factory()->forProject($project)->create([
        'amount' => 4200,
        'currency_id' => $currency->id,
    ]);
    $contract = Contract::factory()->create(['number' => 'C-500', 'title' => 'Аренда стенда']);
    $onContract = Payment::factory()->forContract($contract)->percent(25)->create();

    expect($direct->subjectLabel())->toBe('ITB Berlin 2026')
        ->and($direct->valueLabel())->toContain('USD')
        ->and($onContract->subjectLabel())->toBe('C-500 · Аренда стенда')
        ->and($onContract->valueLabel())->toBe('25%');
});

it('falls back to the contract project when a payment names none', function () {
    $project = Project::factory()->create();
    $contract = Contract::factory()->create(['project_id' => $project->id]);
    $payment = Payment::factory()->forContract($contract)->create();

    expect($payment->resolvedProject()?->id)->toBe($project->id);
});
