<?php

declare(strict_types=1);

namespace App\Modules\Payment\Gateways;

use App\Modules\Payment\Gateways\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\Gateways\Data\GatewayChargeData;
use App\Modules\Payment\Gateways\Data\GatewayResponse;
use Illuminate\Support\Str;

class PaypalGateway implements PaymentGatewayInterface
{
    /**
     * @param  array<string, mixed>  $config  Credentials from config/payments.php.
     */
    public function __construct(protected array $config) {}

    public function charge(GatewayChargeData $data): GatewayResponse
    {
        if (blank($this->config['client_id'] ?? null) || blank($this->config['secret'] ?? null)) {
            return GatewayResponse::failed('Gateway credentials are not configured.', [
                'gateway' => $this->identifier(),
            ]);
        }

        return GatewayResponse::successful('pp_'.Str::lower(Str::random(24)), [
            'gateway' => $this->identifier(),
            'amount' => $data->amount,
            'currency' => $data->currency,
        ]);
    }

    public function identifier(): string
    {
        return 'paypal';
    }
}
