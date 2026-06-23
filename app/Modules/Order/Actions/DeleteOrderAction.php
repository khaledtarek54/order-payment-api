<?php

declare(strict_types=1);

namespace App\Modules\Order\Actions;

use App\Modules\Order\Exceptions\OrderHasPaymentsException;
use App\Modules\Order\Models\Order;

/**
 * Deletes an order, guarding the rule that orders with payments may not be
 * removed (raises OrderHasPaymentsException → 409).
 */
final class DeleteOrderAction
{
    public function execute(Order $order): void
    {
        if ($order->hasPayments()) {
            throw new OrderHasPaymentsException;
        }

        $order->delete();
    }
}
