<?php

declare(strict_types=1);

namespace App\Modules\Order\Actions;

use App\Modules\Order\Models\Order;
use App\Support\ValueObjects\Money;

/**
 * Replaces an order's line items and recomputes its total server-side.
 *
 * Shared by {@see CreateOrderAction} and {@see UpdateOrderAction}; always runs
 * inside the caller's transaction so a partial write can't leave a stale total.
 * Money math is done in integer minor units (via {@see Money}) so summing many
 * line items never accumulates floating-point error.
 */
final class SyncOrderItemsAction
{
    /**
     * @param  array<int, array{product_name: string, quantity: int, unit_price: int|float}>  $items
     */
    public function execute(Order $order, array $items): void
    {
        $total = Money::zero();

        foreach ($items as $item) {
            $unitPrice = Money::fromDecimal($item['unit_price']);
            $lineTotal = $unitPrice->times((int) $item['quantity']);
            $total = $total->plus($lineTotal);

            $order->items()->create([
                'product_name' => $item['product_name'],
                'quantity' => $item['quantity'],
                // Store the normalised unit price so unit_price * quantity always
                // reconciles with line_total (no raw-vs-computed drift).
                'unit_price' => $unitPrice->toDecimalString(),
                'line_total' => $lineTotal->toDecimalString(),
            ]);
        }

        $order->update(['total' => $total]);
    }
}
