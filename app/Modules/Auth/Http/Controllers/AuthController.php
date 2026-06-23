<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Actions\RegisterUserAction;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use App\Modules\Auth\Http\Resources\UserResource;
use App\Support\Http\Controllers\ApiController;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

/**
 * @group Authentication
 *
 * Register, authenticate, and manage the current user's JWT session.
 */
class AuthController extends ApiController
{
    /**
     * Register a new user.
     *
     * Creates a user account and returns a freshly issued JWT.
     *
     * @bodyParam name string required The user's full name. Example: Jane Doe
     * @bodyParam email string required A unique email address. Example: jane@example.com
     * @bodyParam password string required Minimum 8 characters. Example: secret123
     * @bodyParam password_confirmation string required Must match the password. Example: secret123
     *
     * @response 201 {"message":"Registration successful.","data":{"user":{"id":1,"name":"Jane Doe","email":"jane@example.com","created_at":"2026-06-23T00:00:00.000000Z"},"access_token":"eyJ0eXAi...","token_type":"bearer","expires_in":3600}}
     * @response 422 {"message":"This email address is already registered.","errors":{"email":["This email address is already registered."]}}
     */
    public function register(RegisterRequest $request, RegisterUserAction $registerUser): JsonResponse
    {
        $user = $registerUser->execute($request->validated());

        /** @var JWTGuard $guard */
        $guard = auth('api');
        $token = $guard->login($user);

        return $this->respondWithToken($token, 'Registration successful.', 201);
    }

    /**
     * Log in an existing user.
     *
     * Validates credentials and returns a JWT on success.
     *
     * @bodyParam email string required The user's email address. Example: jane@example.com
     * @bodyParam password string required The user's password. Example: secret123
     *
     * @response 200 {"message":"Login successful.","data":{"user":{"id":1,"name":"Jane Doe","email":"jane@example.com","created_at":"2026-06-23T00:00:00.000000Z"},"access_token":"eyJ0eXAi...","token_type":"bearer","expires_in":3600}}
     * @response 401 {"message":"Invalid credentials."}
     * @response 422 {"message":"A valid email address is required.","errors":{"email":["A valid email address is required."]}}
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        /** @var JWTGuard $guard */
        $guard = auth('api');

        if (! $token = $guard->attempt($credentials)) {
            return ApiResponse::problem(401, 'Invalid credentials.', 'invalid_credentials');
        }

        return $this->respondWithToken($token, 'Login successful.');
    }

    /**
     * Get the authenticated user.
     *
     * @authenticated
     *
     * @response 200 {"data":{"id":1,"name":"Jane Doe","email":"jane@example.com","created_at":"2026-06-23T00:00:00.000000Z"}}
     * @response 401 {"message":"Unauthenticated."}
     */
    public function me(): UserResource
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');

        return new UserResource($guard->user());
    }

    /**
     * Log out the authenticated user.
     *
     * Invalidates the current JWT.
     *
     * @authenticated
     *
     * @response 200 {"message":"Successfully logged out."}
     * @response 401 {"message":"Unauthenticated."}
     */
    public function logout(): JsonResponse
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');
        $guard->logout();

        return ApiResponse::message('Successfully logged out.');
    }

    /**
     * Refresh the authenticated user's token.
     *
     * Issues a new JWT and invalidates the previous one.
     *
     * @authenticated
     *
     * @response 200 {"message":"Token refreshed.","data":{"user":{"id":1,"name":"Jane Doe","email":"jane@example.com","created_at":"2026-06-23T00:00:00.000000Z"},"access_token":"eyJ0eXAi...","token_type":"bearer","expires_in":3600}}
     * @response 401 {"message":"Unauthenticated."}
     */
    public function refresh(): JsonResponse
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');
        $token = $guard->refresh();

        return $this->respondWithToken($token, 'Token refreshed.');
    }

    private function respondWithToken(string $token, string $message, int $status = 200): JsonResponse
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');

        return ApiResponse::success(
            [
                'user' => new UserResource($guard->user()),
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $guard->factory()->getTTL() * 60,
            ],
            $message,
            $status,
        );
    }
}
