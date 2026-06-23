<?php

declare(strict_types=1);

namespace App\Modules\Order\Actions;

use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Exceptions\InvalidOrderStatusTransitionException;
use App\Modules\Order\Models\Order;

/**
 * Transitions an order to a target status, enforcing the OrderStatus state
 * machine. Illegal transitions raise InvalidOrderStatusTransitionException (422).
 */
final class ChangeOrderStatusAction
{
    public function execute(Order $order, OrderStatus $target): Order
    {
        if ($order->status !== $target && ! $order->status->canTransitionTo($target)) {
            throw new InvalidOrderStatusTransitionException($order->status, $target);
        }

        $order->update(['status' => $target]);

        return $order;
    }
}
