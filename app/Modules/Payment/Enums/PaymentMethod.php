<?php

declare(strict_types=1);

namespace App\Modules\Payment\Enums;

enum PaymentMethod: string
{
    case CreditCard = 'credit_card';
    case Paypal = 'paypal';

    /**
     * The config/payments.php gateway key this method resolves to.
     */
    public function gatewayKey(): string
    {
        return $this->value;
    }
}
