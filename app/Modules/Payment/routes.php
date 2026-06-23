<?php

declare(strict_types=1);

use App\Modules\Payment\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function (): void {
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');

    Route::get('orders/{order}/payments', [PaymentController::class, 'indexForOrder'])
        ->name('orders.payments.index');
    Route::post('orders/{order}/payments', [PaymentController::class, 'store'])
        ->name('orders.payments.store');
});
