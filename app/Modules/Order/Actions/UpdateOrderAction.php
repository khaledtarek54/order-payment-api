<?php

declare(strict_types=1);

namespace App\Modules\Order\Actions;

use App\Modules\Order\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Updates an order's notes and/or replaces its line items, recomputing the
 * total when items change.
 */
final class UpdateOrderAction
{
    public function __construct(private readonly SyncOrderItemsAction $syncItems) {}

    /**
     * @param  array{notes?: string|null, items?: array<int, array{product_name: string, quantity: int, unit_price: int|float}>}  $data
     */
    public function execute(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data): Order {
            if (array_key_exists('notes', $data)) {
                $order->notes = $data['notes'];
            }

            if (array_key_exists('items', $data)) {
                $order->items()->delete();
                $this->syncItems->execute($order, $data['items']);
            } else {
                $order->save();
            }

            return $order->load('items');
        });
    }
}
