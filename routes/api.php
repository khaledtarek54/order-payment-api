<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
|
| Routes are organised per module. Each module owns its own route file and
| this entry point simply composes them under the shared /api/v1 prefix.
|
*/

Route::prefix('v1')->group(function (): void {
    Route::get('health', fn () => response()->json(['data' => ['status' => 'ok']]))
        ->name('health');

    require __DIR__.'/../app/Modules/Auth/routes.php';
    require __DIR__.'/../app/Modules/Order/routes.php';
    require __DIR__.'/../app/Modules/Payment/routes.php';
});
