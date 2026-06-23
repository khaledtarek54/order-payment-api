<?php

declare(strict_types=1);

namespace App\Modules\Payment\Exceptions;

use App\Support\Exceptions\DomainException;

class PaymentNotRefundableException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Only a successful payment can be refunded.');
    }
}
