<?php

declare(strict_types=1);

use App\Support\Http\Controllers\ApiController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\JsonResource;

/*
|--------------------------------------------------------------------------
| Architecture tests
|--------------------------------------------------------------------------
|
| These turn the project's conventions into enforced, self-documenting
| contracts so the modular structure can't silently drift.
|
*/

arch('modules and support layer declare strict types')
    ->expect(['App\Modules', 'App\Support'])
    ->toUseStrictTypes();

arch('no debugging leftovers ship')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed();

arch('module controllers extend the base ApiController')
    ->expect([
        'App\Modules\Auth\Http\Controllers',
        'App\Modules\Order\Http\Controllers',
        'App\Modules\Payment\Http\Controllers',
    ])
    ->toExtend(ApiController::class);

arch('form requests extend the framework FormRequest')
    ->expect([
        'App\Modules\Auth\Http\Requests',
        'App\Modules\Order\Http\Requests',
        'App\Modules\Payment\Http\Requests',
    ])
    ->toExtend(FormRequest::class);

arch('api resources extend JsonResource')
    ->expect([
        'App\Modules\Auth\Http\Resources',
        'App\Modules\Order\Http\Resources',
        'App\Modules\Payment\Http\Resources',
    ])
    ->toExtend(JsonResource::class);

arch('actions and queries are final, single-purpose classes')
    ->expect([
        'App\Modules\Order\Actions',
        'App\Modules\Payment\Actions',
        'App\Modules\Auth\Actions',
        'App\Modules\Order\Queries',
    ])
    ->toBeFinal();

arch('enums are backed string enums')
    ->expect([
        'App\Modules\Order\Enums',
        'App\Modules\Payment\Enums',
    ])
    ->toBeStringBackedEnums();
