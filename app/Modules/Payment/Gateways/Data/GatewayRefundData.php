<?php

declare(strict_types=1);

namespace App\Modules\Payment\Gateways\Data;

use App\Support\ValueObjects\Money;
use Spatie\LaravelData\Data;

/**
 * Immutable input passed to a gateway's refund(): the original charge reference
 * to reverse and the amount to refund (which may be a partial amount).
 */
class GatewayRefundData extends Data
{
    public function __construct(
        public string $reference,
        public Money $amount,
    ) {}
}
