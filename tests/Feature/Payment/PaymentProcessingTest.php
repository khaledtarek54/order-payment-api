<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Models\Payment;

beforeEach(function (): void {
    // Ensure the credit card gateway has credentials so the simulated charge
    // succeeds in the happy paths (the test env may not define them).
    config()->set('payments.gateways.credit_card.key', 'test-key');
    config()->set('payments.gateways.credit_card.secret', 'test-secret');
});

/**
 * Create a confirmed order owned by the given user, with a couple of items and a total.
 */
function confirmedOrderFor(User $user): Order
{
    $order = Order::factory()->confirmed()->for($user)->create(['total' => 150.00]);

    $order->items()->createMany([
        ['product_name' => 'Widget', 'quantity' => 1, 'unit_price' => 100.00, 'line_total' => 100.00],
        ['product_name' => 'Gadget', 'quantity' => 1, 'unit_price' => 50.00, 'line_total' => 50.00],
    ]);

    return $order;
}

it('processes a payment for a confirmed order', function (): void {
    $user = actingAsUser();
    $order = confirmedOrderFor($user);

    $response = $this->postJson("/api/v1/orders/{$order->getKey()}/payments", [
        'method' => 'credit_card',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.order_id', $order->getKey())
        ->assertJsonPath('data.status', 'successful')
        ->assertJsonPath('data.method', 'credit_card')
        ->assertJsonStructure([
            'data' => ['id', 'order_id', 'status', 'method', 'amount', 'gateway_reference', 'created_at'],
        ]);

    expect($response->json('data.gateway_reference'))->not->toBeNull();

    $this->assertDatabaseHas('payments', [
        'order_id' => $order->getKey(),
        'status' => PaymentStatus::Successful->value,
    ]);

    // A successful payment advances the order to the paid state.
    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('leaves the order confirmed when the payment fails', function (): void {
    $user = actingAsUser();
    $order = confirmedOrderFor($user);

    config()->set('payments.gateways.credit_card.key', '');
    config()->set('payments.gateways.credit_card.secret', '');

    $this->postJson("/api/v1/orders/{$order->getKey()}/payments", ['method' => 'credit_card'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'failed');

    expect($order->fresh()->status)->toBe(OrderStatus::Confirmed);
});

it('records a failed payment when gateway credentials are missing', function (): void {
    $user = actingAsUser();
    $order = confirmedOrderFor($user);

    config()->set('payments.gateways.credit_card.key', '');
    config()->set('payments.gateways.credit_card.secret', '');

    $response = $this->postJson("/api/v1/orders/{$order->getKey()}/payments", [
        'method' => 'credit_card',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'failed');
});

it('rejects payment for a pending (unconfirmed) order with 409', function (): void {
    $user = actingAsUser();
    $order = Order::factory()->for($user)->create(['total' => 100.00]);

    $response = $this->postJson("/api/v1/orders/{$order->getKey()}/payments", [
        'method' => 'credit_card',
    ]);

    $response->assertStatus(409);

    $this->assertDatabaseMissing('payments', ['order_id' => $order->getKey()]);
});

it('forbids paying another user\'s order with 403', function (): void {
    actingAsUser();
    $otherOrder = confirmedOrderFor(createUser());

    $response = $this->postJson("/api/v1/orders/{$otherOrder->getKey()}/payments", [
        'method' => 'credit_card',
    ]);

    $response->assertForbidden();
});

it('rejects an invalid payment method with 422', function (): void {
    $user = actingAsUser();
    $order = confirmedOrderFor($user);

    $response = $this->postJson("/api/v1/orders/{$order->getKey()}/payments", [
        'method' => 'bitcoin',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['method']);
});

it('requires authentication to process a payment', function (): void {
    $order = confirmedOrderFor(createUser());

    $response = $this->postJson("/api/v1/orders/{$order->getKey()}/payments", [
        'method' => 'credit_card',
    ]);

    $response->assertUnauthorized();
});

it('lists payments for an order', function (): void {
    $user = actingAsUser();
    $order = confirmedOrderFor($user);
    Payment::factory()->count(2)->successful()->create(['order_id' => $order->getKey()]);

    $response = $this->getJson("/api/v1/orders/{$order->getKey()}/payments");

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [['id', 'order_id', 'status', 'method', 'amount', 'gateway_reference', 'created_at']],
            'links',
            'meta',
        ]);
});

it('forbids listing payments for another user\'s order with 403', function (): void {
    actingAsUser();
    $otherOrder = confirmedOrderFor(createUser());

    $response = $this->getJson("/api/v1/orders/{$otherOrder->getKey()}/payments");

    $response->assertForbidden();
});

it('lists payments for the authenticated user across their orders', function (): void {
    $user = actingAsUser();
    $order = confirmedOrderFor($user);
    Payment::factory()->successful()->create(['order_id' => $order->getKey()]);

    // A payment belonging to another user must not leak in.
    $otherOrder = confirmedOrderFor(createUser());
    Payment::factory()->successful()->create(['order_id' => $otherOrder->getKey()]);

    $response = $this->getJson('/api/v1/payments');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.order_id', $order->getKey());
});

it('shows a single payment', function (): void {
    $user = actingAsUser();
    $order = confirmedOrderFor($user);
    $payment = Payment::factory()->successful()->create(['order_id' => $order->getKey()]);

    $response = $this->getJson("/api/v1/payments/{$payment->getKey()}");

    $response->assertOk()
        ->assertJsonPath('data.id', $payment->getKey())
        ->assertJsonPath('data.order_id', $order->getKey())
        ->assertJsonPath('data.status', 'successful');
});

it('forbids showing another user\'s payment with 403', function (): void {
    actingAsUser();
    $otherOrder = confirmedOrderFor(createUser());
    $payment = Payment::factory()->successful()->create(['order_id' => $otherOrder->getKey()]);

    $response = $this->getJson("/api/v1/payments/{$payment->getKey()}");

    $response->assertForbidden();
});

it('requires authentication to list payments', function (): void {
    $response = $this->getJson('/api/v1/payments');

    $response->assertUnauthorized();
});
