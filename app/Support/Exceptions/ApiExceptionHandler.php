<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use App\Support\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Centralises how exceptions are rendered for the JSON API. Every error is an
 * RFC 7807 problem document (application/problem+json) with a stable, machine
 * readable `code`, so clients can switch on the error rather than parse prose.
 * Returning null lets Laravel fall back to default handling (e.g. for web).
 */
final class ApiExceptionHandler
{
    public static function register(Exceptions $exceptions): void
    {
        // Business-rule violations carry their own HTTP status, message + code.
        $exceptions->render(fn (DomainException $e, Request $request) => $request->is('api/*')
            ? ApiResponse::problem($e->status(), $e->getMessage(), $e->errorCode())
            : null);

        // Validation failures keep their field-level errors under `errors`.
        $exceptions->render(fn (ValidationException $e, Request $request) => $request->is('api/*')
            ? ApiResponse::problem(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'The given data was invalid.',
                'validation_error',
                $e->errors(),
            )
            : null);

        // Missing / invalid auth.
        $exceptions->render(fn (AuthenticationException $e, Request $request) => $request->is('api/*')
            ? ApiResponse::problem(Response::HTTP_UNAUTHORIZED, 'Unauthenticated.', 'unauthenticated')
            : null);

        // Expired token first (it extends JWTException) ...
        $exceptions->render(fn (TokenExpiredException $e, Request $request) => $request->is('api/*')
            ? ApiResponse::problem(Response::HTTP_UNAUTHORIZED, 'Token has expired.', 'token_expired')
            : null);

        // ... then any other JWT failure (invalid / blacklisted / missing).
        $exceptions->render(fn (JWTException $e, Request $request) => $request->is('api/*')
            ? ApiResponse::problem(Response::HTTP_UNAUTHORIZED, 'Token is invalid.', 'token_invalid')
            : null);

        // Friendly 404 for missing models and unknown routes.
        $exceptions->render(fn (ModelNotFoundException $e, Request $request) => $request->is('api/*')
            ? ApiResponse::problem(Response::HTTP_NOT_FOUND, 'Resource not found.', 'not_found')
            : null);

        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $request->is('api/*')
            ? ApiResponse::problem(Response::HTTP_NOT_FOUND, $e->getMessage() ?: 'Resource not found.', 'not_found')
            : null);
    }
}
