<?php

declare(strict_types=1);

namespace App\Modules\Payment\Actions;

use App\Modules\Order\Models\Order;
use App\Modules\Payment\Enums\PaymentMethod;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Exceptions\OrderNotConfirmedException;
use App\Modules\Payment\Jobs\ProcessPaymentJob;
use App\Modules\Payment\Models\Payment;

/**
 * Initiates payment for a confirmed order: creates a pending Payment and hands
 * the gateway charge to the queue. Business rule: only confirmed orders may be
 * paid (raises OrderNotConfirmedException → 409).
 */
final class ProcessPaymentAction
{
    public function execute(Order $order, PaymentMethod $method): Payment
    {
        if (! $order->isConfirmed()) {
            throw new OrderNotConfirmedException;
        }

        /** @var Payment $payment */
        $payment = $order->payments()->create([
            'status' => PaymentStatus::Pending,
            'method' => $method,
            'amount' => $order->total,
        ]);

        ProcessPaymentJob::dispatch($payment);

        return $payment->refresh();
    }
}
