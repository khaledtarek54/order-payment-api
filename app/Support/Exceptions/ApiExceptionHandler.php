<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use App\Support\Http\Responses\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Centralises how exceptions are rendered for the JSON API so every error
 * response shares one envelope. Returning null lets Laravel fall back to its
 * default handling (e.g. for non-API/web requests).
 */
final class ApiExceptionHandler
{
    public static function register(Exceptions $exceptions): void
    {
        // Business-rule violations carry their own HTTP status + message.
        $exceptions->render(function (DomainException $e, Request $request) {
            return $request->is('api/*')
                ? ApiResponse::error($e->getMessage(), $e->status())
                : null;
        });

        // Expired token first (it extends JWTException) ...
        $exceptions->render(function (TokenExpiredException $e, Request $request) {
            return $request->is('api/*')
                ? ApiResponse::error('Token has expired.', Response::HTTP_UNAUTHORIZED)
                : null;
        });

        // ... then any other JWT failure (invalid / blacklisted / missing).
        $exceptions->render(function (JWTException $e, Request $request) {
            return $request->is('api/*')
                ? ApiResponse::error('Token is invalid.', Response::HTTP_UNAUTHORIZED)
                : null;
        });

        // Friendly 404 for missing models and unknown routes.
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            return $request->is('api/*')
                ? ApiResponse::error('Resource not found.', Response::HTTP_NOT_FOUND)
                : null;
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return $request->is('api/*')
                ? ApiResponse::error('Resource not found.', Response::HTTP_NOT_FOUND)
                : null;
        });
    }
}
