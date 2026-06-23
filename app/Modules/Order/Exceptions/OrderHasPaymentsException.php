<?php

declare(strict_types=1);

namespace App\Modules\Order\Exceptions;

use App\Support\Exceptions\DomainException;

class OrderHasPaymentsException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Order cannot be deleted because it has associated payments.');
    }
}
