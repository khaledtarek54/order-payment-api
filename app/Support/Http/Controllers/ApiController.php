<?php

declare(strict_types=1);

namespace App\Support\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

/**
 * Base controller for all API endpoints. Provides policy authorization
 * ($this->authorize()) and manual validation helpers to every module.
 */
abstract class ApiController extends Controller
{
    use AuthorizesRequests;
    use ValidatesRequests;
}
