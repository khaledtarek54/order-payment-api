<?php

declare(strict_types=1);

use App\Modules\Order\Models\Order;
use App\Modules\Payment\Models\Payment;

it('creates an order and computes the total server-side', function (): void {
    actingAsUser();

    $response = $this->postJson('/api/v1/orders', [
        'total' => 999999,
        'notes' => 'Please hurry.',
        'items' => [
            ['product_name' => 'Widget', 'quantity' => 2, 'unit_price' => 10],
            ['product_name' => 'Gadget', 'quantity' => 3, 'unit_price' => 5],
        ],
    ]);

    $response->assertCreated();
    // 2*10 + 3*5 = 35, never the bogus client total of 999999.
    $response->assertJsonPath('data.total', '35.00');
    $response->assertJsonPath('data.status', 'pending');
    $response->assertJsonPath('data.notes', 'Please hurry.');
    $response->assertJsonStructure([
        'data' => [
            'id',
            'status',
            'total',
            'notes',
            'items' => [['id', 'product_name', 'quantity', 'unit_price', 'line_total']],
            'created_at',
            'updated_at',
        ],
    ]);
});

it('requires authentication to list orders', function (): void {
    $this->getJson('/api/v1/orders')->assertUnauthorized();
});

it('lists only the authenticated user\'s orders and paginates', function (): void {
    $user = actingAsUser();
    Order::factory()->count(3)->create(['user_id' => $user->id]);

    $other = createUser();
    Order::factory()->count(2)->create(['user_id' => $other->id]);

    $response = $this->getJson('/api/v1/orders');

    $response->assertOk();
    $response->assertJsonCount(3, 'data');
    $response->assertJsonPath('meta.total', 3);
    $response->assertJsonStructure([
        'data',
        'links' => ['first', 'last', 'prev', 'next'],
        'meta' => ['current_page', 'last_page', 'per_page', 'total'],
    ]);
});

it('filters the list by status', function (): void {
    $user = actingAsUser();
    Order::factory()->confirmed()->count(2)->create(['user_id' => $user->id]);
    Order::factory()->count(3)->create(['user_id' => $user->id]);

    $response = $this->getJson('/api/v1/orders?filter[status]=confirmed');

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
    $response->assertJsonPath('data.0.status', 'confirmed');
});

it('shows an order to its owner', function (): void {
    $user = actingAsUser();
    $order = Order::factory()->create(['user_id' => $user->id]);

    $response = $this->getJson("/api/v1/orders/{$order->id}");

    $response->assertOk();
    $response->assertJsonPath('data.id', $order->id);
});

it('forbids showing another user\'s order', function (): void {
    actingAsUser();
    $order = Order::factory()->create(['user_id' => createUser()->id]);

    $this->getJson("/api/v1/orders/{$order->id}")->assertForbidden();
});

it('forbids updating another user\'s order', function (): void {
    actingAsUser();
    $order = Order::factory()->create(['user_id' => createUser()->id]);

    $this->putJson("/api/v1/orders/{$order->id}", ['notes' => 'hijacked'])
        ->assertForbidden();
});

it('forbids deleting another user\'s order', function (): void {
    actingAsUser();
    $order = Order::factory()->create(['user_id' => createUser()->id]);

    $this->deleteJson("/api/v1/orders/{$order->id}")->assertForbidden();

    expect(Order::whereKey($order->id)->exists())->toBeTrue();
});

it('updates an order and recomputes the total', function (): void {
    $user = actingAsUser();
    $order = Order::factory()->create(['user_id' => $user->id]);

    $response = $this->putJson("/api/v1/orders/{$order->id}", [
        'notes' => 'Updated note.',
        'items' => [
            ['product_name' => 'Replacement', 'quantity' => 4, 'unit_price' => 2.5],
        ],
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.notes', 'Updated note.');
    $response->assertJsonPath('data.total', '10.00');
});

it('deletes an order without payments', function (): void {
    $user = actingAsUser();
    $order = Order::factory()->create(['user_id' => $user->id]);

    $this->deleteJson("/api/v1/orders/{$order->id}")->assertNoContent();

    expect(Order::whereKey($order->id)->exists())->toBeFalse();
});

it('rejects deleting an order that has payments with a 409', function (): void {
    $user = actingAsUser();
    $order = Order::factory()->confirmed()->create(['user_id' => $user->id]);
    Payment::factory()->create(['order_id' => $order->id]);

    $this->deleteJson("/api/v1/orders/{$order->id}")->assertStatus(409);

    expect(Order::whereKey($order->id)->exists())->toBeTrue();
});

it('validates that at least one item is required when creating', function (): void {
    actingAsUser();

    $response = $this->postJson('/api/v1/orders', ['notes' => 'No items here.']);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('items');
});
