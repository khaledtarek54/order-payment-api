<?php

declare(strict_types=1);

namespace App\Modules\Payment\Listeners;

use App\Modules\Order\Actions\ChangeOrderStatusAction;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Events\PaymentProcessed;
use Illuminate\Support\Facades\Log;

/**
 * Reacts to a processed payment. A successful payment advances its order to the
 * `paid` state through the OrderStatus state machine (which also bumps the
 * order-list cache); failures leave the order confirmed so it can be retried.
 * Kept as a decoupled listener so further side effects (notifications,
 * fulfilment, analytics) can be added without touching the payment flow.
 */
class UpdateOrderAfterPayment
{
    public function __construct(private readonly ChangeOrderStatusAction $changeStatus) {}

    public function handle(PaymentProcessed $event): void
    {
        $payment = $event->payment;

        if ($payment->status === PaymentStatus::Successful) {
            $order = $payment->order;

            // Guard the transition: only a confirmed order advances to paid.
            if ($order->status === OrderStatus::Confirmed) {
                $this->changeStatus->execute($order, OrderStatus::Paid);
            }
        }

        Log::info('Payment processed', [
            'payment_id' => $payment->getKey(),
            'order_id' => $payment->order_id,
            'status' => $payment->status->value,
        ]);
    }
}
