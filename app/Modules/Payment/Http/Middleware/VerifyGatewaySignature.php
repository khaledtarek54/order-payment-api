<?php

declare(strict_types=1);

namespace App\Modules\Payment\Http\Middleware;

use App\Modules\Payment\Exceptions\UnsupportedPaymentMethodException;
use App\Modules\Payment\Gateways\PaymentGatewayManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the public gateway-webhook endpoint. The route carries no auth:api
 * token (the gateway can't hold one); instead it must present a valid HMAC
 * signature of the raw body, verified against the gateway's webhook secret.
 */
final class VerifyGatewaySignature
{
    public const SIGNATURE_HEADER = 'X-Signature';

    public function __construct(private readonly PaymentGatewayManager $gateways) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $gateway = $this->gateways->for((string) $request->route('gateway'));
        } catch (UnsupportedPaymentMethodException) {
            abort(Response::HTTP_NOT_FOUND, 'Unknown payment gateway.');
        }

        $signature = (string) $request->header(self::SIGNATURE_HEADER, '');

        if (! $gateway->verifySignature($request->getContent(), $signature)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid webhook signature.');
        }

        return $next($request);
    }
}
