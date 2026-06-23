<?php

declare(strict_types=1);

namespace App\Modules\Payment\Gateways\Data;

use Spatie\LaravelData\Data;

/**
 * Immutable, typed input passed to a gateway's charge() — decouples gateways
 * from the Payment Eloquent model.
 */
class GatewayChargeData extends Data
{
    public function __construct(
        public string $paymentId,
        public int $orderId,
        public float $amount,
        public string $currency = 'USD',
    ) {}
}
