<?php

declare(strict_types=1);

namespace App\Modules\Payment\Exceptions;

use App\Support\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

class RefundDeclinedException extends DomainException
{
    protected int $status = Response::HTTP_BAD_GATEWAY;

    public function __construct()
    {
        parent::__construct('The refund was declined by the payment gateway.');
    }
}
