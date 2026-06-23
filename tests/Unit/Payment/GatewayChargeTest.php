<?php

declare(strict_types=1);

use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Gateways\CreditCardGateway;
use App\Modules\Payment\Gateways\Data\GatewayChargeData;
use App\Support\ValueObjects\Money;

it('charges successfully with valid credentials', function (): void {
    $gateway = new CreditCardGateway(['key' => 'k', 'secret' => 's']);

    $response = $gateway->charge(new GatewayChargeData('p', 1, Money::fromDecimal(100.0)));

    expect($response->successful)->toBeTrue()
        ->and($response->status)->toBe(PaymentStatus::Successful)
        ->and($response->reference)->not->toBeNull();
});

it('declines when credentials are missing', function (): void {
    $gateway = new CreditCardGateway(['key' => '', 'secret' => '']);

    $response = $gateway->charge(new GatewayChargeData('p', 1, Money::fromDecimal(100.0)));

    expect($response->successful)->toBeFalse()
        ->and($response->status)->toBe(PaymentStatus::Failed);
});
