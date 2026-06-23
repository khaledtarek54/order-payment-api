<?php

declare(strict_types=1);

use App\Modules\Order\Models\Order;

it('confirms a pending order via the status endpoint', function (): void {
    $user = actingAsUser();
    $order = Order::factory()->create(['user_id' => $user->id]);

    $response = $this->patchJson("/api/v1/orders/{$order->id}/status", [
        'status' => 'confirmed',
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.status', 'confirmed');
});

it('rejects an invalid transition from cancelled to confirmed with 422', function (): void {
    $user = actingAsUser();
    $order = Order::factory()->cancelled()->create(['user_id' => $user->id]);

    $response = $this->patchJson("/api/v1/orders/{$order->id}/status", [
        'status' => 'confirmed',
    ]);

    $response->assertStatus(422);

    expect($order->fresh()->status->value)->toBe('cancelled');
});

it('requires authentication to change status', function (): void {
    $order = Order::factory()->create();

    $this->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'confirmed'])
        ->assertUnauthorized();
});

it('forbids changing the status of another user\'s order', function (): void {
    actingAsUser();
    $order = Order::factory()->create(['user_id' => createUser()->id]);

    $this->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'confirmed'])
        ->assertForbidden();
});

it('validates the status value', function (): void {
    $user = actingAsUser();
    $order = Order::factory()->create(['user_id' => $user->id]);

    $this->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'bogus'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');
});
