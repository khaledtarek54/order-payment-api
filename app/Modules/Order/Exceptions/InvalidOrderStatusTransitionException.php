<?php

declare(strict_types=1);

namespace App\Modules\Order\Exceptions;

use App\Modules\Order\Enums\OrderStatus;
use App\Support\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

class InvalidOrderStatusTransitionException extends DomainException
{
    protected int $status = Response::HTTP_UNPROCESSABLE_ENTITY;

    public function __construct(OrderStatus $from, OrderStatus $to)
    {
        parent::__construct("Cannot change order status from '{$from->value}' to '{$to->value}'.");
    }
}
