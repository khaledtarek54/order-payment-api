<?php

declare(strict_types=1);

use App\Modules\Order\Models\Order;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Gateways\PaymentGatewayManager;
use App\Modules\Payment\Jobs\ProcessPaymentJob;
use App\Modules\Payment\Models\Payment;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config()->set('payments.gateways.credit_card.key', 'test-key');
    config()->set('payments.gateways.credit_card.secret', 'test-secret');
});

it('returns the original payment for a repeated Idempotency-Key and never double-charges', function (): void {
    $user = actingAsUser();
    $order = Order::factory()->confirmed()->for($user)->create(['total' => 100.00]);
    $key = 'idem-key-abc-123';

    $first = $this->withHeader('Idempotency-Key', $key)
        ->postJson("/api/v1/orders/{$order->getKey()}/payments", ['method' => 'credit_card'])
        ->assertCreated();

    $second = $this->withHeader('Idempotency-Key', $key)
        ->postJson("/api/v1/orders/{$order->getKey()}/payments", ['method' => 'credit_card'])
        ->assertCreated();

    // Same payment returned, and only one charge exists.
    expect($second->json('data.id'))->toBe($first->json('data.id'));
    $this->assertDatabaseCount('payments', 1);
});

it('replays the original payment even after the order has advanced to paid', function (): void {
    $user = actingAsUser();
    $order = Order::factory()->confirmed()->for($user)->create(['total' => 100.00]);
    $key = 'idem-key-paid';

    $first = $this->withHeader('Idempotency-Key', $key)
        ->postJson("/api/v1/orders/{$order->getKey()}/payments", ['method' => 'credit_card'])
        ->assertCreated();

    // The successful payment moved the order to paid; a same-key retry must
    // still replay (not 409).
    expect($order->fresh()->status->value)->toBe('paid');

    $this->withHeader('Idempotency-Key', $key)
        ->postJson("/api/v1/orders/{$order->getKey()}/payments", ['method' => 'credit_card'])
        ->assertCreated()
        ->assertJsonPath('data.id', $first->json('data.id'));
});

it('does not create a second charge for a duplicate request without an Idempotency-Key', function (): void {
    $user = actingAsUser();
    $order = Order::factory()->confirmed()->for($user)->create(['total' => 100.00]);

    $first = $this->postJson("/api/v1/orders/{$order->getKey()}/payments", ['method' => 'credit_card'])->assertCreated();
    $second = $this->postJson("/api/v1/orders/{$order->getKey()}/payments", ['method' => 'credit_card'])->assertCreated();

    // No key, yet the order still ends up with exactly one payment.
    expect($second->json('data.id'))->toBe($first->json('data.id'));
    $this->assertDatabaseCount('payments', 1);
});

it('queues the gateway charge and returns a pending payment (the async contract)', function (): void {
    Queue::fake();
    $user = actingAsUser();
    $order = Order::factory()->confirmed()->for($user)->create(['total' => 100.00]);

    $this->postJson("/api/v1/orders/{$order->getKey()}/payments", ['method' => 'credit_card'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending');

    Queue::assertPushed(ProcessPaymentJob::class, 1);
});

it('does not re-charge a payment that is no longer pending (retry/redelivery guard)', function (): void {
    $order = Order::factory()->confirmed()->for(createUser())->create(['total' => 50.00]);
    $payment = Payment::factory()->successful()->for($order)->create([
        'gateway_reference' => 'cc_original_reference',
    ]);

    // Re-running the job (as a queue redelivery would) must be a no-op.
    (new ProcessPaymentJob($payment))->handle(app(PaymentGatewayManager::class));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Successful)
        ->and($payment->fresh()->gateway_reference)->toBe('cc_original_reference');
});

it('marks a still-pending payment as failed when the job ultimately fails', function (): void {
    $order = Order::factory()->confirmed()->for(createUser())->create(['total' => 50.00]);
    $payment = Payment::factory()->for($order)->create(['status' => PaymentStatus::Pending]);

    (new ProcessPaymentJob($payment))->failed(new RuntimeException('gateway unreachable'));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed);
});
