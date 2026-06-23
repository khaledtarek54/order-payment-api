<?php

declare(strict_types=1);

namespace App\Modules\Payment\Jobs;

use App\Modules\Payment\Events\PaymentProcessed;
use App\Modules\Payment\Gateways\Data\GatewayChargeData;
use App\Modules\Payment\Gateways\PaymentGatewayManager;
use App\Modules\Payment\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs the gateway charge off the request cycle. With QUEUE_CONNECTION=sync
 * (the default for easy testing/demo) it executes inline; switch to the
 * `database` connection + a worker for true async processing — no code change.
 */
class ProcessPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Payment $payment) {}

    public function handle(PaymentGatewayManager $manager): void
    {
        $payment = $this->payment;

        $response = $manager->for($payment->method)->charge(new GatewayChargeData(
            paymentId: (string) $payment->getKey(),
            orderId: (int) $payment->order_id,
            amount: $payment->amount,
        ));

        $payment->update([
            'status' => $response->status,
            'gateway_reference' => $response->reference,
            'gateway_response' => $response->raw + ['message' => $response->message],
        ]);

        PaymentProcessed::dispatch($payment->refresh());
    }
}
