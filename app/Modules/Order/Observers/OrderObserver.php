<?php

declare(strict_types=1);

namespace App\Modules\Order\Observers;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Support\OrderCache;

/**
 * Invalidates a user's cached order lists whenever one of their orders changes.
 */
class OrderObserver
{
    public function saved(Order $order): void
    {
        OrderCache::bump((int) $order->user_id);
    }

    public function deleted(Order $order): void
    {
        OrderCache::bump((int) $order->user_id);
    }
}
