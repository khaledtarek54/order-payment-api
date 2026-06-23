<?php

declare(strict_types=1);

namespace App\Support\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tiny helper to keep success/error envelopes consistent across the API.
 *
 * Success responses always expose a `data` key; entity payloads are usually
 * produced by API Resources (which also wrap in `data`), so the shape stays
 * uniform. Errors expose `message` and an optional `errors` map.
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        $payload = [];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        $payload['data'] = $data;

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function message(string $message, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    /**
     * Error envelope. Errors are emitted as RFC 7807 problem documents.
     *
     * @param  array<string, mixed>  $errors
     */
    public static function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        return self::problem($status, $message, errors: $errors);
    }

    /**
     * Build an RFC 7807 (application/problem+json) error response.
     *
     * @param  array<string, mixed>  $errors  Field-level validation errors, if any.
     */
    public static function problem(
        int $status,
        string $detail,
        ?string $code = null,
        array $errors = [],
        ?string $title = null,
    ): JsonResponse {
        $title ??= Response::$statusTexts[$status] ?? 'Error';

        $payload = [
            'type' => '/problems/'.($code ?? Str::slug($title)),
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ];

        if ($code !== null) {
            $payload['code'] = $code;
        }

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status)
            ->header('Content-Type', 'application/problem+json');
    }
}
