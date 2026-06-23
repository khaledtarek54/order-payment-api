<?php

declare(strict_types=1);

namespace App\Modules\Payment\Gateways\Data;

use App\Support\ValueObjects\Money;
use Spatie\LaravelData\Data;

/**
 * Immutable, typed input passed to a gateway's charge() — decouples gateways
 * from the Payment Eloquent model. The amount is a Money value object so the
 * currency and exact minor-unit value travel together.
 */
class GatewayChargeData extends Data
{
    public function __construct(
        public string $paymentId,
        public int $orderId,
        public Money $amount,
    ) {}
}
