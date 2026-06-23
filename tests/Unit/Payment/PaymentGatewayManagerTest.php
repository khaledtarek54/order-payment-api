<?php

declare(strict_types=1);

use App\Modules\Payment\Exceptions\UnsupportedPaymentMethodException;
use App\Modules\Payment\Gateways\CreditCardGateway;
use App\Modules\Payment\Gateways\PaymentGatewayManager;
use App\Modules\Payment\Gateways\PaypalGateway;

it('resolves the credit card gateway', function (): void {
    $manager = app(PaymentGatewayManager::class);

    expect($manager->for('credit_card'))->toBeInstanceOf(CreditCardGateway::class);
});

it('resolves the paypal gateway', function (): void {
    $manager = app(PaymentGatewayManager::class);

    expect($manager->for('paypal'))->toBeInstanceOf(PaypalGateway::class);
});

it('throws for an unsupported payment method', function (): void {
    $manager = app(PaymentGatewayManager::class);

    $manager->for('bitcoin');
})->throws(UnsupportedPaymentMethodException::class);
