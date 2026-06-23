<?php

declare(strict_types=1);

namespace App\Modules\Order\Actions;

use App\Models\User;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Creates an order with its line items. The total is always computed
 * server-side; any client-supplied total is ignored.
 */
final class CreateOrderAction
{
    public function __construct(private readonly SyncOrderItemsAction $syncItems) {}

    /**
     * @param  array{notes?: string|null, items: array<int, array{product_name: string, quantity: int, unit_price: int|float}>}  $data
     */
    public function execute(User $user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data): Order {
            /** @var Order $order */
            $order = $user->orders()->create([
                'status' => OrderStatus::Pending,
                'notes' => $data['notes'] ?? null,
                'total' => 0,
            ]);

            $this->syncItems->execute($order, $data['items']);

            return $order->load('items');
        });
    }
}
