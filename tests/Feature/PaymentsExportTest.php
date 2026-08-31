<?php

use App\Enums\PaymentSubject;
use App\Exports\PaymentsExport;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Support\ExportPermission;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('allows an export only for super admins and explicit permission holders', function () {
    Permission::findOrCreate('export_payment', 'web');

    $plain = User::factory()->create();
    actingAs($plain->fresh());
    expect(ExportPermission::allows('export_payment'))->toBeFalse();

    $plain->givePermissionTo('export_payment');
    actingAs($plain->fresh());
    expect(ExportPermission::allows('export_payment'))->toBeTrue();

    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
    actingAs($admin->fresh());
    expect(ExportPermission::allows('export_payment'))->toBeTrue();
});

it('exports the visible payments with sequential numbering and mapped columns', function () {
    $contract = Contract::factory()->approved()->create([
        'number' => 'DEMO-2026-001',
        'title' => 'Office lease',
    ]);
    Payment::factory()->create(['contract_id' => $contract->id, 'percent' => 40]);
    Payment::factory()->create(['contract_id' => $contract->id, 'percent' => 60]);

    $export = new PaymentsExport(Payment::query());

    expect($export->query()->get())->toHaveCount(2);

    $row = $export->map(Payment::query()->where('percent', 40)->first());

    expect($row[0])->toBe(1)                                              // sequential №
        ->and($row[1])->toBe(PaymentSubject::Contract->label())           // what it settles
        ->and($row[2])->toBe('DEMO-2026-001 · Office lease')              // the contract itself
        ->and($row[3])->toBe('40%')                                       // formatted percent (canonical)
        ->and($row[4])->toBeNull();                                       // no absolute sum on a contract payment
});

it('exports a direct project payment as a sum rather than a share', function () {
    $currency = Currency::factory()->create(['short_name' => 'UZS']);
    $project = Project::factory()->create(['name' => 'ITB Berlin 2026']);
    $payment = Payment::factory()->forProject($project)->create([
        'amount' => 12_500_000,
        'currency_id' => $currency->id,
        'purpose' => 'Аренда оборудования',
    ]);

    $row = (new PaymentsExport(Payment::query()))->map($payment->fresh());

    expect($row[1])->toBe(PaymentSubject::Project->label())
        ->and($row[2])->toBe('ITB Berlin 2026')
        ->and($row[3])->toBeNull()
        ->and($row[4])->toContain('UZS');
});

it('limits the payments export to what the user may see', function () {
    $manager = User::factory()->create();

    $own = Contract::factory()->approved()->create(['responsible_id' => $manager->id]);
    $other = Contract::factory()->approved()->create();
    Payment::factory()->create(['contract_id' => $own->id]);
    Payment::factory()->create(['contract_id' => $other->id]);

    $rows = (new PaymentsExport(Payment::query()->visibleTo($manager->fresh())))->query()->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->contract_id)->toBe($own->id);
});

it('shows the payments export action only to permission holders', function () {
    Permission::findOrCreate('view_any_payment', 'web');
    Permission::findOrCreate('export_payment', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('view_any_payment');
    actingAs($user->fresh());

    Livewire::test(ListPayments::class)->assertTableActionHidden('exportXlsx');

    $user->givePermissionTo('export_payment');
    actingAs($user->fresh());

    Livewire::test(ListPayments::class)->assertTableActionVisible('exportXlsx');
});
