<?php

declare(strict_types=1);

namespace App\Modules\Payment\Exceptions;

use App\Support\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

class RefundExceedsPaymentException extends DomainException
{
    protected int $status = Response::HTTP_UNPROCESSABLE_ENTITY;

    public function __construct()
    {
        parent::__construct('The refund amount exceeds the remaining refundable balance.');
    }
}
