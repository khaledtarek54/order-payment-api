<?php

declare(strict_types=1);

namespace App\Modules\Order\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    /**
     * Statuses this status is allowed to transition into. A confirmed order
     * advances to `paid` once a payment succeeds (driven by the payment flow);
     * `paid` and `cancelled` are terminal.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Paid, self::Cancelled],
            self::Paid => [],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
