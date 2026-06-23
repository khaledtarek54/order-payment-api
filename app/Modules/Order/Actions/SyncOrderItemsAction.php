<?php

declare(strict_types=1);

namespace App\Modules\Order\Actions;

use App\Modules\Order\Models\Order;

/**
 * Replaces an order's line items and recomputes its total server-side.
 *
 * Shared by {@see CreateOrderAction} and {@see UpdateOrderAction}; always runs
 * inside the caller's transaction so a partial write can't leave a stale total.
 */
final class SyncOrderItemsAction
{
    /**
     * @param  array<int, array{product_name: string, quantity: int, unit_price: int|float}>  $items
     */
    public function execute(Order $order, array $items): void
    {
        $total = 0.0;

        foreach ($items as $item) {
            $lineTotal = round($item['quantity'] * $item['unit_price'], 2);
            $total += $lineTotal;

            $order->items()->create([
                'product_name' => $item['product_name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'line_total' => $lineTotal,
            ]);
        }

        $order->update(['total' => round($total, 2)]);
    }
}
