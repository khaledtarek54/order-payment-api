<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Models\Payment;

beforeEach(function (): void {
    config()->set('payments.gateways.credit_card.key', 'test-key');
    config()->set('payments.gateways.credit_card.secret', 'test-secret');
});

function successfulPaymentFor(User $user, float $amount = 100.0): Payment
{
    $order = Order::factory()->confirmed()->for($user)->create(['total' => $amount]);

    return Payment::factory()->successful()->for($order)->create([
        'amount' => $amount,
        'gateway_reference' => 'cc_ref_original',
    ]);
}

it('fully refunds a successful payment', function (): void {
    $payment = successfulPaymentFor(actingAsUser());

    $this->postJson("/api/v1/payments/{$payment->getKey()}/refund")
        ->assertOk()
        ->assertJsonPath('data.status', 'refunded')
        ->assertJsonPath('data.refunded_amount', '100.00');
});

it('partially refunds and then completes the refund', function (): void {
    $payment = successfulPaymentFor(actingAsUser());

    $this->postJson("/api/v1/payments/{$payment->getKey()}/refund", ['amount' => 40])
        ->assertOk()
        ->assertJsonPath('data.status', 'partially_refunded')
        ->assertJsonPath('data.refunded_amount', '40.00');

    $this->postJson("/api/v1/payments/{$payment->getKey()}/refund", ['amount' => 60])
        ->assertOk()
        ->assertJsonPath('data.status', 'refunded')
        ->assertJsonPath('data.refunded_amount', '100.00');
});

it('rejects a refund that exceeds the balance only by accumulation', function (): void {
    $payment = successfulPaymentFor(actingAsUser(), 100.0);

    $this->postJson("/api/v1/payments/{$payment->getKey()}/refund", ['amount' => 60])
        ->assertOk()
        ->assertJsonPath('data.status', 'partially_refunded');

    // Remaining is 40, so a second 60 must be rejected (remaining = amount - refunded).
    $this->postJson("/api/v1/payments/{$payment->getKey()}/refund", ['amount' => 60])
        ->assertStatus(422)
        ->assertJsonPath('code', 'refund_exceeds_payment');

    expect($payment->fresh()->refunded_amount->toDecimalString())->toBe('60.00');
});

it('rejects a refund that exceeds the remaining balance', function (): void {
    $payment = successfulPaymentFor(actingAsUser());

    $this->postJson("/api/v1/payments/{$payment->getKey()}/refund", ['amount' => 150])
        ->assertStatus(422)
        ->assertJsonPath('code', 'refund_exceeds_payment');

    expect($payment->fresh()->refunded_amount->toDecimalString())->toBe('0.00');
});

it('rejects refunding a payment that is not successful', function (): void {
    $user = actingAsUser();
    $order = Order::factory()->confirmed()->for($user)->create(['total' => 100.00]);
    $payment = Payment::factory()->for($order)->create(['status' => PaymentStatus::Pending, 'amount' => 100.00]);

    $this->postJson("/api/v1/payments/{$payment->getKey()}/refund")
        ->assertStatus(409)
        ->assertJsonPath('code', 'payment_not_refundable');
});

it('forbids refunding another user\'s payment', function (): void {
    actingAsUser();
    $payment = successfulPaymentFor(createUser());

    $this->postJson("/api/v1/payments/{$payment->getKey()}/refund")
        ->assertForbidden();
});

it('reports a gateway-declined refund and leaves the payment untouched', function (): void {
    $payment = successfulPaymentFor(actingAsUser());

    config()->set('payments.gateways.credit_card.key', '');
    config()->set('payments.gateways.credit_card.secret', '');

    $this->postJson("/api/v1/payments/{$payment->getKey()}/refund")
        ->assertStatus(502)
        ->assertJsonPath('code', 'refund_declined');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Successful)
        ->and($payment->fresh()->refunded_amount->toDecimalString())->toBe('0.00');
});

it('requires authentication to refund', function (): void {
    $payment = successfulPaymentFor(createUser());

    $this->postJson("/api/v1/payments/{$payment->getKey()}/refund")
        ->assertUnauthorized();
});
