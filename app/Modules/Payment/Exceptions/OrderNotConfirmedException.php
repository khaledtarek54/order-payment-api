<?php

declare(strict_types=1);

namespace App\Modules\Payment\Exceptions;

use App\Support\Exceptions\DomainException;

class OrderNotConfirmedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Payments can only be processed for orders in the confirmed status.');
    }
}
