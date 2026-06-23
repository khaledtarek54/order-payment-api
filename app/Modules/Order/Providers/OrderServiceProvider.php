<?php

declare(strict_types=1);

namespace App\Modules\Order\Providers;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Observers\OrderObserver;
use App\Modules\Order\Policies\OrderPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class OrderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Order::observe(OrderObserver::class);
        Gate::policy(Order::class, OrderPolicy::class);
    }
}
