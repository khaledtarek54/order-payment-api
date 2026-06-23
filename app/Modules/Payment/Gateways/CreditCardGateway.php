<?php

declare(strict_types=1);

namespace App\Modules\Payment\Gateways;

use App\Modules\Payment\Gateways\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\Gateways\Data\GatewayChargeData;
use App\Modules\Payment\Gateways\Data\GatewayResponse;
use Illuminate\Support\Str;

class CreditCardGateway implements PaymentGatewayInterface
{
    /**
     * @param  array<string, mixed>  $config  Credentials from config/payments.php.
     */
    public function __construct(protected array $config) {}

    public function charge(GatewayChargeData $data): GatewayResponse
    {
        // Simulated processing. Missing credentials => a deterministic decline,
        // which also gives tests an easy, real-looking failure path to assert.
        if (blank($this->config['key'] ?? null) || blank($this->config['secret'] ?? null)) {
            return GatewayResponse::failed('Gateway credentials are not configured.', [
                'gateway' => $this->identifier(),
            ]);
        }

        return GatewayResponse::successful('cc_'.Str::lower(Str::random(24)), [
            'gateway' => $this->identifier(),
            'amount' => $data->amount->toDecimalString(),
            'currency' => $data->amount->currency,
        ]);
    }

    public function identifier(): string
    {
        return 'credit_card';
    }
}
