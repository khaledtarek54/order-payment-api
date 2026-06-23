<?php

declare(strict_types=1);

namespace App\Modules\Order\Services;

use App\Models\User;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Exceptions\InvalidOrderStatusTransitionException;
use App\Modules\Order\Exceptions\OrderHasPaymentsException;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Support\OrderCache;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * All order business logic lives here so controllers stay thin and the rules
 * (server-side totals, status transitions, delete guards) are unit-testable.
 */
class OrderService
{
    private const LIST_TTL = 30;

    /**
     * Paginated, filterable list scoped to the given user. Cached per
     * user + query fingerprint and invalidated on write via OrderObserver.
     *
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginate(User $user, int $perPage, string $fingerprint): LengthAwarePaginator
    {
        $key = OrderCache::listKey((int) $user->getKey(), $fingerprint);

        return Cache::remember($key, self::LIST_TTL, fn (): LengthAwarePaginator => QueryBuilder::for(Order::class)
            ->allowedFilters(AllowedFilter::exact('status'))
            ->allowedSorts('created_at', 'total', 'status')
            ->defaultSort('-created_at')
            ->where('user_id', $user->getKey())
            ->with('items')
            ->paginate($perPage)
            ->appends(request()->query()));
    }

    /**
     * @param  array{notes?: string|null, items: array<int, array{product_name: string, quantity: int, unit_price: int|float}>}  $data
     */
    public function create(User $user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data): Order {
            /** @var Order $order */
            $order = $user->orders()->create([
                'status' => OrderStatus::Pending,
                'notes' => $data['notes'] ?? null,
                'total' => 0,
            ]);

            $this->syncItems($order, $data['items']);

            return $order->load('items');
        });
    }

    /**
     * @param  array{notes?: string|null, items?: array<int, array{product_name: string, quantity: int, unit_price: int|float}>}  $data
     */
    public function update(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data): Order {
            if (array_key_exists('notes', $data)) {
                $order->notes = $data['notes'];
            }

            if (array_key_exists('items', $data)) {
                $order->items()->delete();
                $this->syncItems($order, $data['items']);
            } else {
                $order->save();
            }

            return $order->load('items');
        });
    }

    public function changeStatus(Order $order, OrderStatus $target): Order
    {
        if ($order->status !== $target && ! $order->status->canTransitionTo($target)) {
            throw new InvalidOrderStatusTransitionException($order->status, $target);
        }

        $order->update(['status' => $target]);

        return $order;
    }

    public function delete(Order $order): void
    {
        if ($order->hasPayments()) {
            throw new OrderHasPaymentsException;
        }

        $order->delete();
    }

    /**
     * Recreates an order's items and recomputes the total server-side. The
     * client-supplied total (if any) is always ignored.
     *
     * @param  array<int, array{product_name: string, quantity: int, unit_price: int|float}>  $items
     */
    private function syncItems(Order $order, array $items): void
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
