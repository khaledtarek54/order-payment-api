<?php

declare(strict_types=1);

namespace App\Modules\Payment\Gateways;

use App\Modules\Payment\Enums\PaymentMethod;
use App\Modules\Payment\Exceptions\UnsupportedPaymentMethodException;
use App\Modules\Payment\Gateways\Contracts\PaymentGatewayInterface;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the right gateway Strategy from the config registry at runtime.
 * This is the single place that maps a payment method to its implementation.
 */
class PaymentGatewayManager
{
    /**
     * @param  array<string, mixed>  $config  The full config/payments.php array.
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $config,
    ) {}

    public function for(PaymentMethod|string $method): PaymentGatewayInterface
    {
        $key = $method instanceof PaymentMethod ? $method->value : $method;

        $gateway = $this->config['gateways'][$key] ?? null;

        if (! is_array($gateway) || ! isset($gateway['class'])) {
            throw new UnsupportedPaymentMethodException($key);
        }

        // Gateways receive their own config slice (api keys / secrets).
        $instance = $this->container->make($gateway['class'], ['config' => $gateway]);

        if (! $instance instanceof PaymentGatewayInterface) {
            throw new UnsupportedPaymentMethodException($key);
        }

        return $instance;
    }

    /**
     * @return array<int, string>
     */
    public function supportedMethods(): array
    {
        return array_keys($this->config['gateways'] ?? []);
    }
}
