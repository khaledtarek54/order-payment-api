<?php

declare(strict_types=1);

namespace App\Modules\Payment\Listeners;

use App\Modules\Payment\Events\PaymentProcessed;
use Illuminate\Support\Facades\Log;

/**
 * Reacts to a processed payment. Kept as a decoupled listener so side effects
 * (notifications, fulfilment, analytics) can be added without touching the
 * payment flow. For the simulation it records the outcome.
 */
class UpdateOrderAfterPayment
{
    public function handle(PaymentProcessed $event): void
    {
        $payment = $event->payment;

        Log::info('Payment processed', [
            'payment_id' => $payment->getKey(),
            'order_id' => $payment->order_id,
            'status' => $payment->status->value,
        ]);
    }
}
