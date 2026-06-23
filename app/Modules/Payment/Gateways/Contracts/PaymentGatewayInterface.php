<?php

declare(strict_types=1);

namespace App\Modules\Payment\Gateways\Contracts;

use App\Modules\Payment\Gateways\Data\GatewayChargeData;
use App\Modules\Payment\Gateways\Data\GatewayRefundData;
use App\Modules\Payment\Gateways\Data\GatewayResponse;

/**
 * The Strategy contract every payment gateway implements.
 *
 * Adding a new gateway means: implement this interface, register the class in
 * config/payments.php, and add the method to the PaymentMethod enum. Nothing in
 * the controllers, services, jobs or events needs to change.
 */
interface PaymentGatewayInterface
{
    public function charge(GatewayChargeData $data): GatewayResponse;

    public function refund(GatewayRefundData $data): GatewayResponse;

    public function identifier(): string;

    /**
     * Verify an inbound webhook's signature against the raw request body, using
     * the gateway's configured secret. Implementations MUST use a timing-safe
     * comparison (hash_equals) to resist timing attacks.
     */
    public function verifySignature(string $payload, string $signature): bool;
}
