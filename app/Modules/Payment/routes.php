<?php

declare(strict_types=1);

use App\Modules\Payment\Http\Controllers\GatewayWebhookController;
use App\Modules\Payment\Http\Controllers\PaymentController;
use App\Modules\Payment\Http\Middleware\VerifyGatewaySignature;
use Illuminate\Support\Facades\Route;

// Public gateway settlement webhook — no auth token; authenticity is proven by
// the HMAC signature checked in VerifyGatewaySignature.
Route::post('payments/webhook/{gateway}', [GatewayWebhookController::class, 'handle'])
    ->middleware(VerifyGatewaySignature::class)
    ->name('payments.webhook');

Route::middleware('auth:api')->group(function (): void {
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::post('payments/{payment}/refund', [PaymentController::class, 'refund'])->name('payments.refund');

    Route::get('orders/{order}/payments', [PaymentController::class, 'indexForOrder'])
        ->name('orders.payments.index');
    Route::post('orders/{order}/payments', [PaymentController::class, 'store'])
        ->name('orders.payments.store');
});
