<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests run against a fresh database; Unit tests get the Laravel
| TestCase (so the container/config are available) without DB refresh.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function createUser(array $attributes = []): User
{
    return User::factory()->create($attributes);
}

/**
 * Authenticate (a new or given) user on the JWT "api" guard for the next request.
 */
function actingAsUser(?User $user = null): User
{
    $user ??= createUser();

    test()->actingAs($user, 'api');

    return $user;
}
