<?php

declare(strict_types=1);

namespace App\Modules\Payment\Services;

use App\Modules\Order\Models\Order;
use App\Modules\Payment\Enums\PaymentMethod;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Exceptions\OrderNotConfirmedException;
use App\Modules\Payment\Jobs\ProcessPaymentJob;
use App\Modules\Payment\Models\Payment;

class PaymentService
{
    /**
     * Process a payment for an order.
     *
     * Business rule: only confirmed orders may be paid. A pending Payment is
     * created up front, then handed to the queue/gateway for processing.
     */
    public function process(Order $order, PaymentMethod $method): Payment
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
