<?php

use App\Enums\RequisitionStatus;
use App\Exports\RequisitionsExport;
use App\Filament\Resources\Contracts\Pages\ListContracts;
use App\Filament\Resources\Requisitions\Pages\ListRequisitions;
use App\Models\Contract;
use App\Models\Order;
use App\Models\Project;
use App\Models\Requisition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('opens every register with the freshest record on top', function (string $page, string $permission, string $model) {
    Storage::fake('local');
    $user = userWithPermission($permission, 'view_all_contracts', 'view_all_requisitions');
    actingAs($user);

    $owner = $model === Contract::class ? ['responsible_id' => $user->id] : [];
    $stale = $model::factory()->create($owner);
    $fresh = $model::factory()->create($owner);

    $stale->forceFill(['updated_at' => now()->subYear()])->saveQuietly();
    $fresh->forceFill(['updated_at' => now()])->saveQuietly();

    Livewire::test($page)->assertCanSeeTableRecords([$fresh, $stale], inOrder: true);
})->with([
    'contracts' => [ListContracts::class, 'view_any_contract', Contract::class],
    'requisitions' => [ListRequisitions::class, 'view_any_requisition', Requisition::class],
]);

it('filters contracts by the order their project was issued under', function () {
    Storage::fake('local');
    actingAs(userWithPermission('view_any_contract', 'view_all_contracts'));

    $order = Order::factory()->committee()->create(['number' => '119-АФ']);
    $otherOrder = Order::factory()->committee()->create(['number' => '77-АФ']);

    $wanted = Contract::factory()->approved()->create([
        'number' => 'ON-ORDER',
        'project_id' => Project::factory()->create(['order_id' => $order->id])->id,
    ]);
    $unrelated = Contract::factory()->approved()->create([
        'number' => 'OFF-ORDER',
        'project_id' => Project::factory()->create(['order_id' => $otherOrder->id])->id,
    ]);
    $orderless = Contract::factory()->approved()->create(['number' => 'NO-PROJECT', 'project_id' => null]);

    Livewire::test(ListContracts::class)
        ->filterTable('order_id', $order->id)
        ->assertCanSeeTableRecords([$wanted])
        ->assertCanNotSeeTableRecords([$unrelated, $orderless]);
});

it('exports only the requisitions the active filters leave on screen', function () {
    $author = userWithPermission('view_any_requisition', 'view_all_requisitions', 'export_requisition');
    actingAs($author);

    $draft = Requisition::factory()->create(['title' => 'Черновик заявки', 'status' => RequisitionStatus::Draft]);
    $approved = Requisition::factory()->approved()->create(['title' => 'Согласованная заявка']);

    $page = Livewire::test(ListRequisitions::class)
        ->assertActionVisible('exportXlsx')
        ->filterTable('status', RequisitionStatus::Approved->value);

    $exported = (new RequisitionsExport($page->instance()->getFilteredTableQuery()))
        ->query()->pluck('title')->all();

    expect($exported)->toBe([$approved->title])
        ->not->toContain($draft->title);
});

it('hides the requisition export from anyone without the permission', function () {
    actingAs(userWithPermission('view_any_requisition'));

    Livewire::test(ListRequisitions::class)->assertActionHidden('exportXlsx');
});
