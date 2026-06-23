<?php

declare(strict_types=1);

namespace App\Modules\Payment\Http\Controllers;

use App\Modules\Payment\Actions\SettlePaymentAction;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Http\Requests\GatewayWebhookRequest;
use App\Modules\Payment\Models\Payment;
use App\Support\Http\Controllers\ApiController;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Payments
 *
 * Receives asynchronous settlement callbacks from payment gateways. This is a
 * public endpoint (gateways hold no user token); its authenticity is proven by
 * an HMAC signature, verified by the VerifyGatewaySignature middleware.
 */
class GatewayWebhookController extends ApiController
{
    /**
     * Handle a gateway webhook.
     *
     * @urlParam gateway string required The gateway key. One of: credit_card, paypal. Example: credit_card
     *
     * @header X-Signature string required HMAC-SHA256 of the raw request body, keyed by the gateway webhook secret.
     *
     * @bodyParam reference string required The gateway reference of the payment to settle. Example: cc_abc123
     * @bodyParam status string required The settled status. One of: successful, failed. Example: successful
     *
     * @response 200 {"message":"Webhook processed."}
     * @response 202 {"message":"Webhook acknowledged; no matching payment."}
     * @response 401 {"message":"Invalid webhook signature."}
     * @response 404 {"message":"Unknown payment gateway."}
     * @response 422 {"message":"The webhook status must be either successful or failed."}
     */
    public function handle(GatewayWebhookRequest $request, string $gateway, SettlePaymentAction $settle): JsonResponse
    {
        $payment = Payment::query()
            ->where('gateway_reference', $request->validated('reference'))
            ->first();

        // Ack unknown references so the gateway stops retrying a payment we
        // don't recognise (it isn't an error on our side).
        if ($payment === null) {
            return ApiResponse::message('Webhook acknowledged; no matching payment.', Response::HTTP_ACCEPTED);
        }

        $settle->execute(
            $payment,
            PaymentStatus::from($request->validated('status')),
            $request->json()->all(),
        );

        return ApiResponse::message('Webhook processed.');
    }
}
