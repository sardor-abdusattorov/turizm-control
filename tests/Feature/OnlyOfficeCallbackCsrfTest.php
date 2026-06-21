<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;

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
