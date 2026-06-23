<?php

declare(strict_types=1);

namespace App\Modules\Order\Policies;

use App\Models\User;
use App\Modules\Order\Models\Order;

/**
 * Ownership-based authorization. A user may only see and mutate their own
 * orders (and, by extension, the payments nested under them).
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return $this->owns($user, $order);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Order $order): bool
    {
        return $this->owns($user, $order);
    }

    public function delete(User $user, Order $order): bool
    {
        return $this->owns($user, $order);
    }

    private function owns(User $user, Order $order): bool
    {
        return $user->getKey() === $order->user_id;
    }
}
