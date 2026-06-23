<?php

declare(strict_types=1);

it('attaches the api rate limiter to API routes', function (): void {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', '60');
});

it('throttles API traffic past the per-minute limit', function (): void {
    foreach (range(1, 60) as $ignored) {
        $this->getJson('/api/v1/health')->assertOk();
    }

    $this->getJson('/api/v1/health')->assertStatus(429);
});

it('echoes a generated X-Request-Id header', function (): void {
    $response = $this->getJson('/api/v1/health');

    $response->assertOk();
    expect($response->headers->get('X-Request-Id'))->not->toBeEmpty();
});

it('honours an inbound X-Request-Id', function (): void {
    $response = $this->getJson('/api/v1/health', ['X-Request-Id' => 'trace-abc-123']);

    expect($response->headers->get('X-Request-Id'))->toBe('trace-abc-123');
});
