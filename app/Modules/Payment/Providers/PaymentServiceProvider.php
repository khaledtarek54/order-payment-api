<?php

declare(strict_types=1);

namespace App\Modules\Payment\Providers;

use App\Modules\Payment\Events\PaymentProcessed;
use App\Modules\Payment\Gateways\PaymentGatewayManager;
use App\Modules\Payment\Listeners\UpdateOrderAfterPayment;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            PaymentGatewayManager::class,
            fn (Application $app): PaymentGatewayManager => new PaymentGatewayManager(
                $app,
                config('payments'),
            ),
        );
    }

    public function boot(): void
    {
        Event::listen(PaymentProcessed::class, UpdateOrderAfterPayment::class);
    }
}
