<?php

declare(strict_types=1);

use App\Modules\Auth\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])
        ->middleware('throttle:auth')
        ->name('auth.register');

    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:auth')
        ->name('auth.login');

    Route::middleware('auth:api')->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
    });
});
