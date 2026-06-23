<?php

declare(strict_types=1);

use PHPOpenSourceSaver\JWTAuth\JWTGuard;

it('registers a user and returns a token', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('message', 'Registration successful.')
        ->assertJsonPath('data.user.email', 'jane@example.com')
        ->assertJsonPath('data.user.name', 'Jane Doe')
        ->assertJsonPath('data.token_type', 'bearer')
        ->assertJsonStructure([
            'message',
            'data' => [
                'user' => ['id', 'name', 'email', 'created_at'],
                'access_token',
                'token_type',
                'expires_in',
            ],
        ]);

    expect($response->json('data.access_token'))->toBeString()->not->toBeEmpty();
    $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
});

it('rejects registration when the password is too short', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('rejects registration when the password confirmation does not match', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'different123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('rejects registration when required fields are missing', function (): void {
    $response = $this->postJson('/api/v1/auth/register', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('rejects registration with a duplicate email', function (): void {
    createUser(['email' => 'taken@example.com']);

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'taken@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('logs in with valid credentials and returns a token', function (): void {
    createUser([
        'email' => 'jane@example.com',
        'password' => 'secret123',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'secret123',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Login successful.')
        ->assertJsonPath('data.user.email', 'jane@example.com')
        ->assertJsonPath('data.token_type', 'bearer')
        ->assertJsonStructure([
            'message',
            'data' => [
                'user' => ['id', 'name', 'email', 'created_at'],
                'access_token',
                'token_type',
                'expires_in',
            ],
        ]);

    expect($response->json('data.access_token'))->toBeString()->not->toBeEmpty();
});

it('rejects login with invalid credentials', function (): void {
    createUser([
        'email' => 'jane@example.com',
        'password' => 'secret123',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('message', 'Invalid credentials.');
});

it('rejects login when validation fails', function (): void {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'not-an-email',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

it('rejects the me endpoint without a token', function (): void {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertStatus(401);
});

it('returns the authenticated user from the me endpoint', function (): void {
    $user = actingAsUser(createUser(['email' => 'jane@example.com']));

    $response = $this->getJson('/api/v1/auth/me');

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', 'jane@example.com')
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'created_at'],
        ]);
});

it('logs out the authenticated user', function (): void {
    actingAsUser();

    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Successfully logged out.');
});

it('rejects logout without a token', function (): void {
    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertStatus(401);
});

it('refreshes the token for the authenticated user', function (): void {
    $user = createUser([
        'email' => 'jane@example.com',
        'password' => 'secret123',
    ]);

    /** @var JWTGuard $guard */
    $guard = auth('api');
    $originalToken = $guard->login($user);

    $response = $this->withHeader('Authorization', "Bearer {$originalToken}")
        ->postJson('/api/v1/auth/refresh');

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Token refreshed.')
        ->assertJsonPath('data.token_type', 'bearer')
        ->assertJsonStructure([
            'message',
            'data' => [
                'user' => ['id', 'name', 'email', 'created_at'],
                'access_token',
                'token_type',
                'expires_in',
            ],
        ]);

    $newToken = $response->json('data.access_token');
    expect($newToken)->toBeString()->not->toBeEmpty();
    expect($newToken)->not->toBe($originalToken);
});
