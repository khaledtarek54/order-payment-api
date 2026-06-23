<?php

declare(strict_types=1);

use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Enums\PaymentMethod;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Models\Payment;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

const WEBHOOK_SECRET = 'whsec_test';

beforeEach(function (): void {
    config()->set('payments.gateways.credit_card.webhook_secret', WEBHOOK_SECRET);
});

/**
 * POST a webhook with an explicit raw body so we control the exact bytes that
 * are HMAC-signed (and thus what the server verifies).
 *
 * @param  array<string, mixed>  $payload
 */
function postSignedWebhook(TestCase $test, string $gateway, array $payload, ?string $forceSignature = null): TestResponse
{
    $content = (string) json_encode($payload);
    $signature = $forceSignature ?? hash_hmac('sha256', $content, WEBHOOK_SECRET);

    return $test->call(
        'POST',
        "/api/v1/payments/webhook/{$gateway}",
        [], [], [],
        [
            'HTTP_X_SIGNATURE' => $signature,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ],
        $content,
    );
}

function pendingPaymentWithReference(string $reference): Payment
{
    $order = Order::factory()->confirmed()->for(createUser())->create(['total' => 100.00]);

    return Payment::factory()->for($order)->create([
        'status' => PaymentStatus::Pending,
        'method' => PaymentMethod::CreditCard,
        'gateway_reference' => $reference,
    ]);
}

it('settles a pending payment from a validly signed webhook and advances the order', function (): void {
    $payment = pendingPaymentWithReference('cc_ref_777');

    postSignedWebhook($this, 'credit_card', ['reference' => 'cc_ref_777', 'status' => 'successful'])
        ->assertOk()
        ->assertJsonPath('message', 'Webhook processed.');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Successful)
        ->and($payment->order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('rejects a webhook with an invalid signature and leaves the payment untouched', function (): void {
    $payment = pendingPaymentWithReference('cc_ref_777');

    postSignedWebhook($this, 'credit_card', ['reference' => 'cc_ref_777', 'status' => 'successful'], 'forged-signature')
        ->assertStatus(401);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('returns 404 for an unknown gateway', function (): void {
    postSignedWebhook($this, 'bitcoin', ['reference' => 'whatever', 'status' => 'successful'])
        ->assertStatus(404);
});

it('acknowledges a webhook for an unknown reference with 202', function (): void {
    postSignedWebhook($this, 'credit_card', ['reference' => 'does-not-exist', 'status' => 'successful'])
        ->assertStatus(202);
});

it('is idempotent: a redelivered webhook does not change an already-settled payment', function (): void {
    $payment = pendingPaymentWithReference('cc_ref_777');

    postSignedWebhook($this, 'credit_card', ['reference' => 'cc_ref_777', 'status' => 'successful'])->assertOk();
    postSignedWebhook($this, 'credit_card', ['reference' => 'cc_ref_777', 'status' => 'failed'])->assertOk();

    // The second event must NOT flip an already-successful payment.
    expect($payment->fresh()->status)->toBe(PaymentStatus::Successful);
});

it('will not settle a credit_card payment via a paypal-signed webhook (gateway isolation)', function (): void {
    config()->set('payments.gateways.paypal.webhook_secret', 'pp_secret');
    $payment = pendingPaymentWithReference('cc_ref_777'); // a credit_card payment

    $payload = ['reference' => 'cc_ref_777', 'status' => 'successful'];
    $content = (string) json_encode($payload);
    $sig = hash_hmac('sha256', $content, 'pp_secret');

    // Validly signed for paypal, but the reference belongs to a credit_card payment.
    $this->call('POST', '/api/v1/payments/webhook/paypal', [], [], [],
        ['HTTP_X_SIGNATURE' => $sig, 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'], $content)
        ->assertStatus(202);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('rejects a valid signature submitted with a different body (byte-exact verification)', function (): void {
    pendingPaymentWithReference('cc_ref_777');

    $signed = (string) json_encode(['reference' => 'cc_ref_777', 'status' => 'successful']);
    $sig = hash_hmac('sha256', $signed, WEBHOOK_SECRET);
    $tampered = (string) json_encode(['reference' => 'cc_ref_777', 'status' => 'failed']);

    $this->call('POST', '/api/v1/payments/webhook/credit_card', [], [], [],
        ['HTTP_X_SIGNATURE' => $sig, 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'], $tampered)
        ->assertStatus(401);
});

it('validates the webhook status (signature still required)', function (): void {
    pendingPaymentWithReference('cc_ref_777');

    postSignedWebhook($this, 'credit_card', ['reference' => 'cc_ref_777', 'status' => 'pending'])
        ->assertStatus(422);
});
