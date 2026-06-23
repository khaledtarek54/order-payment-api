<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base class for business-rule violations.
 *
 * Each concrete exception carries the HTTP status that best describes the
 * conflict (defaults to 409 Conflict). The API exception handler renders these
 * into the standard error envelope automatically.
 */
abstract class DomainException extends RuntimeException
{
    protected int $status = Response::HTTP_CONFLICT;

    public function status(): int
    {
        return $this->status;
    }
}
