<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    ->use(RefreshDatabase::class)
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
 * Create a user whose current team is a shared team with the given role.
 *
 * @return array{0: User, 1: Team}
 */
function memberOfTeam(TeamRole $role = TeamRole::Member): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => $role->value]);
    $user->switchTeam($team);

    return [$user, $team];
}

/**
 * Get a complete, valid set of assisted person form values.
 *
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function assistedPersonFormValues(array $overrides = []): array
{
    $values = array_merge([
        'name' => 'Maria da Silva',
        'birth_date' => '1980-05-10',
        'email' => 'maria@example.com',
        'phone' => '(11) 98765-4321',
        'postal_code' => '01310-100',
        'street' => 'Avenida Paulista',
        'number' => '1000',
        'complement' => 'Apto 42',
        'district' => 'Bela Vista',
        'city' => 'São Paulo',
        'state' => 'SP',
    ], $overrides);

    return collect($values)
        ->mapWithKeys(fn (string $value, string $key): array => ["form.{$key}" => $value])
        ->all();
}
