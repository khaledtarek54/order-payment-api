<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base class for business-rule violations.
 *
 * Each concrete exception carries the HTTP status that best describes the
 * conflict (defaults to 409 Conflict) and a stable, machine-readable error code
 * (derived from the class name) that clients can switch on. The API exception
 * handler renders these into an RFC 7807 problem document automatically.
 */
abstract class DomainException extends RuntimeException
{
    protected int $status = Response::HTTP_CONFLICT;

    public function status(): int
    {
        return $this->status;
    }

    /**
     * A stable snake_case code, e.g. OrderNotConfirmedException → order_not_confirmed.
     * Override to pin a code independently of the class name.
     */
    public function errorCode(): string
    {
        return Str::snake(Str::beforeLast(class_basename(static::class), 'Exception'));
    }
}
