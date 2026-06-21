<?php

use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\post;

uses(RefreshDatabase::class);

/**
 * OnlyOffice posts the edited document back to these routes with no CSRF
 * token, so they must be in the CSRF exemption list. Renaming the routes
 * without updating bootstrap/app.php silently breaks saving (419, with
 * nothing in the log), so guard the exemption here.
 */
it('exempts the OnlyOffice save-callback routes from CSRF verification', function (string $path) {
    $excluded = app(PreventRequestForgery::class)->getExcludedPaths();

    $request = Request::create($path, 'POST');

    $isExempt = collect($excluded)->contains(
        fn (string $pattern): bool => $request->is(trim($pattern, '/')),
    );

    expect($isExempt)->toBeTrue();
})->with([
    'http://localhost/contracts/6/save-callback',
    'http://localhost/contract-templates/2/save-callback',
    'http://localhost/orders/3/save-callback',
]);

it('rejects an OnlyOffice callback with no JWT when verification is configured', function () {
    Storage::fake('local');
    config(['onlyoffice.jwt_secret' => 'test-secret']);

    $contract = Contract::factory()->create([
        'responsible_id' => User::factory()->create()->id,
    ]);

    // Body has the right shared_key but no signed token — the handler must
    // refuse so an attacker who learned the shared_key cannot forge saves.
    post(
        route('contracts.save-callback', [
            'contract' => $contract,
            'shared_key' => $contract->document_key,
        ]),
        [
            'status' => 2,
            'url' => 'http://onlyoffice/cache/files/anything.docx',
        ],
    )->assertForbidden();
});
