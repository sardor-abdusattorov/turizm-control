<?php

use App\Models\User;
use App\Services\OnlyOffice\JwtSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create an active user holding the given Shield permission(s) (web guard).
 */
function userWithPermission(string ...$permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user->fresh();
}

/**
 * Sign an OnlyOffice save-callback payload with the configured JWT secret.
 * After enabling JWT verification in the handler, raw bodies are rejected —
 * tests that POST to save-callback routes need to wrap the data with a
 * valid token using this helper.
 *
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function signOnlyOfficeCallback(array $data): array
{
    $signer = new JwtSigner((string) config('onlyoffice.jwt_secret'));

    return $data + ['token' => $signer->encode($data)];
}
