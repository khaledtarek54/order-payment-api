<?php

declare(strict_types=1);

use App\Modules\Order\Models\Order;

it('renders domain rule violations as RFC 7807 problem documents', function (): void {
    $user = actingAsUser();
    // A pending order cannot be paid -> OrderNotConfirmedException (409).
    $order = Order::factory()->for($user)->create(['total' => 50.00]);

    $this->postJson("/api/v1/orders/{$order->getKey()}/payments", ['method' => 'credit_card'])
        ->assertStatus(409)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'order_not_confirmed')
        ->assertJsonPath('status', 409)
        ->assertJsonStructure(['type', 'title', 'status', 'detail', 'code']);
});

it('renders validation failures as problem documents that keep field errors', function (): void {
    actingAsUser();

    $this->postJson('/api/v1/orders', ['notes' => 'no items'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonValidationErrors('items')
        ->assertJsonStructure(['type', 'title', 'status', 'detail', 'errors']);
});
