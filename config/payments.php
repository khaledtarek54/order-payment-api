<?php

declare(strict_types=1);

use App\Modules\Payment\Gateways\CreditCardGateway;
use App\Modules\Payment\Gateways\PaypalGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Gateway
    |--------------------------------------------------------------------------
    |
    | Used when a payment request does not specify a method. Must match one of
    | the keys in the "gateways" map below.
    |
    */

    'default' => env('PAYMENT_DEFAULT_GATEWAY', 'credit_card'),

    /*
    |--------------------------------------------------------------------------
    | Gateway Registry  (the extensibility seam)
    |--------------------------------------------------------------------------
    |
    | Each entry maps a payment method to a Strategy implementation plus its
    | credentials (pulled from .env). The PaymentGatewayManager resolves the
    | right strategy from this map at runtime.
    |
    | To add a new gateway you only touch THREE places — and none of them are
    | controllers or services:
    |   1. Create a class implementing PaymentGatewayInterface.
    |   2. Add an entry here with its class + credentials.
    |   3. Add the method to the PaymentMethod enum.
    |
    */

    'gateways' => [

        'credit_card' => [
            'class' => CreditCardGateway::class,
            'key' => env('CREDIT_CARD_API_KEY'),
            'secret' => env('CREDIT_CARD_API_SECRET'),
            'webhook_secret' => env('CREDIT_CARD_WEBHOOK_SECRET'),
        ],

        'paypal' => [
            'class' => PaypalGateway::class,
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'secret' => env('PAYPAL_SECRET'),
            'webhook_secret' => env('PAYPAL_WEBHOOK_SECRET'),
        ],

    ],

];
