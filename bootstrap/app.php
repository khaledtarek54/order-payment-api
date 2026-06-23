<?php

use App\Support\Exceptions\ApiExceptionHandler;
use App\Support\Http\Middleware\AssignRequestId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Laravel's bare `api` group does NOT auto-attach a throttler, so wire
        // the 'api' rate limiter (defined in AppServiceProvider) explicitly, and
        // stamp every API request/response with a correlation id.
        $middleware->api(prepend: [
            AssignRequestId::class,
            ThrottleRequests::class.':api',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        ApiExceptionHandler::register($exceptions);
    })->create();
