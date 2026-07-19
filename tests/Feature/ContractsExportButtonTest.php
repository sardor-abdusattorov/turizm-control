<?php

use App\Filament\Resources\Contracts\Pages\ListContracts;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('greys out the contracts export until the list actually shows contracts', function () {
    $user = User::factory()->create();

    foreach (['view_any_contract', 'view_contract', 'export_contract'] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }

    actingAs($user->fresh());

    Livewire::test(ListContracts::class)->assertActionDisabled('exportXlsx');

    Contract::factory()->create(['responsible_id' => $user->id]);

    Livewire::test(ListContracts::class)->assertActionEnabled('exportXlsx');
});
