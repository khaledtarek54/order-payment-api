<?php

declare(strict_types=1);

namespace App\Modules\Payment\Exceptions;

use App\Support\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

class UnsupportedPaymentMethodException extends DomainException
{
    protected int $status = Response::HTTP_UNPROCESSABLE_ENTITY;

    public function __construct(string $method)
    {
        parent::__construct("Unsupported payment method '{$method}'.");
    }
}
