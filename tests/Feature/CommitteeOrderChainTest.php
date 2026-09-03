<?php

use App\Enums\OrderScope;
use App\Filament\Resources\Orders\BaseOrderResource;
use App\Filament\Resources\Orders\Pages\CreateCommitteeOrder;
use App\Filament\Resources\Orders\Pages\CreatePrCenterOrder;
use App\Filament\Resources\Orders\Pages\ViewCommitteeOrder;
use App\Filament\Resources\Orders\Pages\ViewPrCenterOrder;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function orderAuthorActing(): User
{
    $user = User::factory()->create();

    foreach (['view_any_order', 'view_order', 'create_order', 'update_order'] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }

    actingAs($user->fresh());

    return $user;
}

it('stamps the committee scope on an order created from the committee registry', function () {
    Storage::fake('local');
    orderAuthorActing();

    Livewire::test(CreateCommitteeOrder::class)
        ->fillForm([
            'number' => '119-АФ',
            'title' => 'О проведении национального стенда',
            'issued_at' => '2026-03-04',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Order::firstWhere('number', '119-АФ')->scope)->toBe(OrderScope::Committee);
});

it('offers a committee order as the basis of a PR centre order and stores the link', function () {
    Storage::fake('local');
    orderAuthorActing();

    $committee = Order::factory()->committee()->create(['number' => '06-АФ', 'status' => true]);

    Livewire::test(CreatePrCenterOrder::class)
        ->assertFormFieldVisible('basis_order_id')
        ->fillForm([
            'number' => 'ПР-14',
            'title' => 'Во исполнение приказа комитета',
            'issued_at' => '2026-03-10',
            'basis_order_id' => $committee->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $internal = Order::firstWhere('number', 'ПР-14');

    expect($internal->scope)->toBe(OrderScope::PrCenter)
        ->and($internal->basis_order_id)->toBe($committee->id)
        ->and($internal->basisOrder->number)->toBe('06-АФ')
        ->and($committee->derivedOrders()->pluck('number')->all())->toBe(['ПР-14']);
});

it('never asks a committee order for a basis of its own', function () {
    Storage::fake('local');
    orderAuthorActing();

    Livewire::test(CreateCommitteeOrder::class)
        ->assertFormFieldHidden('basis_order_id');
});

it('only offers committee orders as a basis', function () {
    Storage::fake('local');
    orderAuthorActing();

    $committee = Order::factory()->committee()->create(['number' => '77-АФ', 'status' => true]);
    $otherCentreOrder = Order::factory()->prCenter()->create(['number' => 'ПР-99', 'status' => true]);
    $archived = Order::factory()->committee()->create(['number' => '01-АФ', 'status' => false]);

    $offered = collect(Order::committeeBasisOptions())->flatMap(fn (array $group): array => array_keys($group));

    expect($offered)->toContain($committee->id)
        ->not->toContain($otherCentreOrder->id)
        ->not->toContain($archived->id);
});

it('shows the basis as a link on the PR centre order page', function () {
    Storage::fake('local');
    orderAuthorActing();

    $committee = Order::factory()->committee()->create([
        'number' => '49-АФ',
        'title' => 'О пресс-турах',
        'file_path' => null,
    ]);
    $internal = Order::factory()->prCenter()->create([
        'basis_order_id' => $committee->id,
        'file_path' => null,
    ]);

    $html = Livewire::test(ViewPrCenterOrder::class, ['record' => $internal->id])->html();

    expect($html)->toContain('49-АФ')
        ->toContain(__('app.label.committee_order_basis'));
});

it('lists the derived PR centre orders on the committee order page', function () {
    Storage::fake('local');
    orderAuthorActing();

    $committee = Order::factory()->committee()->create(['file_path' => null]);
    $derived = Order::factory()->prCenter()->create([
        'number' => 'ПР-21',
        'basis_order_id' => $committee->id,
        'file_path' => null,
    ]);
    $unrelated = Order::factory()->prCenter()->create(['number' => 'ПР-22', 'file_path' => null]);

    $html = Livewire::test(ViewCommitteeOrder::class, ['record' => $committee->id])->html();

    expect($html)
        ->toContain(__('app.label.pr_center_order_plural'))
        ->toContain('ПР-21')
        ->toContain(BaseOrderResource::urlFor($derived))
        ->not->toContain('ПР-22')
        ->not->toContain(BaseOrderResource::urlFor($unrelated));
});

it('says so on the committee order page when nothing is derived from it', function () {
    Storage::fake('local');
    orderAuthorActing();

    $committee = Order::factory()->committee()->create(['file_path' => null]);

    expect(Livewire::test(ViewCommitteeOrder::class, ['record' => $committee->id])->html())
        ->toContain(__('app.message.no_derived_orders'));
});

it('does not offer the derived orders row on a PR centre order', function () {
    Storage::fake('local');
    orderAuthorActing();

    $committee = Order::factory()->committee()->create(['file_path' => null]);
    $internal = Order::factory()->prCenter()->create([
        'basis_order_id' => $committee->id,
        'file_path' => null,
    ]);

    expect(Livewire::test(ViewPrCenterOrder::class, ['record' => $internal->id])->html())
        ->not->toContain(__('app.label.pr_center_order_plural'));
});

it('keeps a PR centre order when its basis is deleted', function () {
    Storage::fake('local');

    $committee = Order::factory()->committee()->create();
    $internal = Order::factory()->prCenter()->create(['basis_order_id' => $committee->id]);

    $committee->delete();

    expect($internal->fresh())->not->toBeNull()
        ->and($internal->fresh()->basis_order_id)->toBeNull();
});
