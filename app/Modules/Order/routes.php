<?php

declare(strict_types=1);

use App\Modules\Order\Http\Controllers\OrderController;
use App\Modules\Order\Http\Controllers\OrderStatusController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function (): void {
    Route::apiResource('orders', OrderController::class);

    Route::patch('orders/{order}/status', [OrderStatusController::class, 'update'])
        ->name('orders.status.update');
});
