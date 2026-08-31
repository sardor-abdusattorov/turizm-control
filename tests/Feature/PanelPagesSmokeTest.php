<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * Every register in the panel, opened by somebody allowed to see it. Guards
 * the two failures Filament keeps quiet about: a page that only breaks once
 * rendered, and a translation key rendering as its own name.
 */
it('opens every register without an error or a raw translation key', function (string $slug, string $permission) {
    actingAs(userWithPermission($permission));

    $response = get(Filament::getPanel('admin')->getPath().'/'.$slug);

    $response->assertSuccessful();

    expect($response->getContent())
        ->not->toContain('app.label.')
        ->not->toContain('app.approval.')
        ->not->toContain('app.message.')
        ->not->toContain('app.action.')
        ->not->toContain('app.helper.');
})->with([
    ['contracts', 'view_any_contract'],
    ['requisitions', 'view_any_requisition'],
    ['payments', 'view_any_payment'],
    ['committee-orders', 'view_any_order'],
    ['pr-center-orders', 'view_any_order'],
    ['contacts', 'view_any_contact'],
    ['sponsors', 'view_any_sponsor'],
    ['press-tours', 'view_any_press_tour'],
    ['contract-types', 'view_any_contract_type'],
    ['currencies', 'view_any_currency'],
    ['departments', 'view_any_department'],
    ['positions', 'view_any_position'],
    ['users', 'view_any_user'],
]);
