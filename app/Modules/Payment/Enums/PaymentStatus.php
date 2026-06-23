<?php

declare(strict_types=1);

namespace App\Modules\Payment\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Successful = 'successful';
    case Failed = 'failed';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    /** A payment that money can still be refunded against. */
    public function isRefundable(): bool
    {
        return $this === self::Successful || $this === self::PartiallyRefunded;
    }
}
