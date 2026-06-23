<?php

use App\Filament\Resources\Contacts\Pages\ListContacts;
use App\Filament\Resources\Contracts\Pages\ListContracts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function tableViewer(string $viewPermission): User
{
    $user = User::factory()->create();
    Permission::findOrCreate($viewPermission, 'web');
    $user->givePermissionTo($viewPermission);

    return $user->fresh();
}

it('hides contract bulk delete from a viewer without the delete permission', function () {
    actingAs(tableViewer('view_any_contract'));

    Livewire::test(ListContracts::class)->assertTableBulkActionHidden('delete');
});

it('hides contact bulk delete from a viewer without the delete permission', function () {
    actingAs(tableViewer('view_any_contact'));

    Livewire::test(ListContacts::class)->assertTableBulkActionHidden('delete');
});

it('shows contract bulk delete once the user holds the delete permission', function () {
    $user = tableViewer('view_any_contract');
    $user->givePermissionTo(Permission::findOrCreate('delete_contract', 'web'));

    actingAs($user->fresh());

    Livewire::test(ListContracts::class)->assertTableBulkActionVisible('delete');
});
