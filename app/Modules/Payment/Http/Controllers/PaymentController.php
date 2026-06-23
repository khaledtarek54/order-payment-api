<?php

declare(strict_types=1);

namespace App\Modules\Payment\Http\Controllers;

use App\Modules\Order\Models\Order;
use App\Modules\Payment\Actions\ProcessPaymentAction;
use App\Modules\Payment\Enums\PaymentMethod;
use App\Modules\Payment\Http\Requests\ProcessPaymentRequest;
use App\Modules\Payment\Http\Resources\PaymentResource;
use App\Modules\Payment\Models\Payment;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Payments
 */
class PaymentController extends ApiController
{
    /**
     * List payments
     *
     * List all payments belonging to the authenticated user's orders, paginated.
     *
     * @authenticated
     *
     * @queryParam per_page integer Number of results per page (max 100). Example: 15
     *
     * @response 200 {"data":[{"id":"9b1c...","order_id":1,"status":"successful","method":"credit_card","amount":"100.00","gateway_reference":"cc_abc123","created_at":"2026-06-23T12:00:00.000000Z"}],"links":{"first":"...","last":"...","prev":null,"next":null},"meta":{"current_page":1,"per_page":15,"total":1}}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $payments = Payment::query()
            ->whereHas('order', fn ($q) => $q->where('user_id', $request->user()->getKey()))
            ->latest()
            ->paginate($perPage);

        return PaymentResource::collection($payments);
    }

    /**
     * List payments for an order
     *
     * List all payments for a specific order owned by the authenticated user, paginated.
     *
     * @authenticated
     *
     * @urlParam order integer required The ID of the order. Example: 1
     *
     * @queryParam per_page integer Number of results per page (max 100). Example: 15
     *
     * @response 200 {"data":[{"id":"9b1c...","order_id":1,"status":"successful","method":"credit_card","amount":"100.00","gateway_reference":"cc_abc123","created_at":"2026-06-23T12:00:00.000000Z"}],"links":{"first":"...","last":"...","prev":null,"next":null},"meta":{"current_page":1,"per_page":15,"total":1}}
     * @response 403 {"message":"This action is unauthorized."}
     */
    public function indexForOrder(Request $request, Order $order): AnonymousResourceCollection
    {
        $this->authorize('view', $order);

        $perPage = min((int) $request->integer('per_page', 15), 100);

        return PaymentResource::collection($order->payments()->latest()->paginate($perPage));
    }

    /**
     * Process a payment
     *
     * Process a payment for a confirmed order. The order must be confirmed before
     * it can be paid, otherwise a 409 is returned.
     *
     * @authenticated
     *
     * @urlParam order integer required The ID of the order to pay. Example: 1
     *
     * @header Idempotency-Key A client-generated key (e.g. a UUID). Repeating a request with the same key returns the original payment instead of charging again. Example: 1f0a2b3c-4d5e-6f70-8a9b-0c1d2e3f4a5b
     *
     * @bodyParam method string required The payment method. One of: credit_card, paypal. Example: credit_card
     *
     * @response 201 {"data":{"id":"9b1c...","order_id":1,"status":"successful","method":"credit_card","amount":"100.00","gateway_reference":"cc_abc123","created_at":"2026-06-23T12:00:00.000000Z"}}
     * @response 403 {"message":"This action is unauthorized."}
     * @response 409 {"message":"The order must be confirmed before it can be paid."}
     * @response 422 {"message":"The selected payment method is not supported.","errors":{"method":["The selected payment method is not supported."]}}
     */
    public function store(
        ProcessPaymentRequest $request,
        Order $order,
        ProcessPaymentAction $processPayment,
    ): JsonResponse {
        $this->authorize('view', $order);

        $payment = $processPayment->execute(
            $order,
            PaymentMethod::from($request->validated()['method']),
            $request->header('Idempotency-Key'),
        );

        return (new PaymentResource($payment))->response()->setStatusCode(201);
    }

    /**
     * Show a payment
     *
     * Retrieve a single payment belonging to one of the authenticated user's orders.
     *
     * @authenticated
     *
     * @urlParam payment string required The UUID of the payment. Example: 9b1c2d3e-4f5a-6b7c-8d9e-0f1a2b3c4d5e
     *
     * @response 200 {"data":{"id":"9b1c...","order_id":1,"status":"successful","method":"credit_card","amount":"100.00","gateway_reference":"cc_abc123","created_at":"2026-06-23T12:00:00.000000Z"}}
     * @response 403 {"message":"This action is unauthorized."}
     * @response 404 {"message":"No query results for model [App\\Modules\\Payment\\Models\\Payment]."}
     */
    public function show(Payment $payment): PaymentResource
    {
        $this->authorize('view', $payment->order);

        return new PaymentResource($payment->load('order'));
    }
}
