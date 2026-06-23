<?php

declare(strict_types=1);

namespace App\Support\Http\Responses;

use Illuminate\Http\JsonResponse;

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
     * @param  array<string, mixed>  $errors
     */
    public static function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $payload = ['message' => $message];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
