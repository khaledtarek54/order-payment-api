<?php

use App\Modules\Auth\Providers\AuthServiceProvider;
use App\Modules\Order\Providers\OrderServiceProvider;
use App\Modules\Payment\Providers\PaymentServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    OrderServiceProvider::class,
    PaymentServiceProvider::class,
];
