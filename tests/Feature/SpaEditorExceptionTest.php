<?php

use App\Filament\Resources\ContractTemplates\Pages\ViewContractTemplate;
use App\Models\ContractTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('renders the editor link without wire:navigate so it leaves the SPA cleanly', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    foreach (['view_any_contract_template', 'view_contract_template', 'update_contract_template'] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }

    $template = ContractTemplate::factory()->create();
    Storage::disk('public')->put($template->template_file, 'fake-docx');

    actingAs($user->fresh());

    $html = Livewire::test(ViewContractTemplate::class, ['record' => $template->id])->html();

    // The editor link is present...
    expect($html)->toContain('editor?mode=view');

    // ...but rendered WITHOUT wire:navigate, so it does a full page load and
    // doesn't corrupt the SPA DOM (sidebar/scroll) on return.
    expect($html)->not->toContain('editor?mode=view" wire:navigate');
});
