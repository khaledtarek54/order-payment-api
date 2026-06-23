<?php

declare(strict_types=1);

namespace App\Modules\Order\Http\Controllers;

use App\Modules\Order\Actions\CreateOrderAction;
use App\Modules\Order\Actions\DeleteOrderAction;
use App\Modules\Order\Actions\UpdateOrderAction;
use App\Modules\Order\Http\Requests\StoreOrderRequest;
use App\Modules\Order\Http\Requests\UpdateOrderRequest;
use App\Modules\Order\Http\Resources\OrderResource;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Queries\ListOrdersQuery;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @group Orders
 *
 * Create, browse, and manage the authenticated user's orders. Totals are always
 * computed server-side from the supplied line items.
 */
class OrderController extends ApiController
{
    /**
     * List orders.
     *
     * Returns a paginated list of the authenticated user's orders. Supports
     * filtering (filter[status]) and sorting (sort=-created_at,total,status).
     *
     * @authenticated
     *
     * @queryParam filter[status] string Filter by status (pending, confirmed, cancelled). Example: confirmed
     * @queryParam sort string Sort by created_at, total or status (prefix with - to reverse). Example: -created_at
     * @queryParam per_page int Items per page (max 100). Example: 15
     *
     * @response 200 {"data":[{"id":1,"status":"pending","total":"49.98","notes":null,"items":[{"id":1,"product_name":"Widget","quantity":2,"unit_price":"24.99","line_total":"49.98"}],"created_at":"2026-06-23T00:00:00.000000Z","updated_at":"2026-06-23T00:00:00.000000Z"}],"links":{"first":"...","last":"...","prev":null,"next":null},"meta":{"current_page":1,"last_page":1,"per_page":15,"total":1}}
     * @response 401 {"message":"Unauthenticated."}
     */
    public function index(Request $request, ListOrdersQuery $orders): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Order::class);

        $perPage = min((int) $request->integer('per_page', 15), 100);
        $fingerprint = md5((string) $request->getQueryString());

        return OrderResource::collection(
            $orders->execute($request->user(), $perPage, $fingerprint),
        );
    }

    /**
     * Create an order.
     *
     * Persists a new order with its line items. The order total is computed
     * server-side; any client-supplied total is ignored.
     *
     * @authenticated
     *
     * @bodyParam notes string An optional note for the order. Example: Leave at the front desk.
     * @bodyParam items object[] required The order line items. Example: [{"product_name":"Widget","quantity":2,"unit_price":24.99}]
     * @bodyParam items[].product_name string required The product name. Example: Widget
     * @bodyParam items[].quantity int required The quantity (min 1). Example: 2
     * @bodyParam items[].unit_price number required The unit price (min 0). Example: 24.99
     *
     * @response 201 {"data":{"id":1,"status":"pending","total":"49.98","notes":null,"items":[{"id":1,"product_name":"Widget","quantity":2,"unit_price":"24.99","line_total":"49.98"}],"created_at":"2026-06-23T00:00:00.000000Z","updated_at":"2026-06-23T00:00:00.000000Z"}}
     * @response 401 {"message":"Unauthenticated."}
     * @response 422 {"message":"At least one order item is required.","errors":{"items":["At least one order item is required."]}}
     */
    public function store(StoreOrderRequest $request, CreateOrderAction $createOrder): JsonResponse
    {
        $this->authorize('create', Order::class);

        $order = $createOrder->execute($request->user(), $request->validated());

        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    /**
     * Show an order.
     *
     * Returns a single order with its items and payments.
     *
     * @authenticated
     *
     * @urlParam order int required The order ID. Example: 1
     *
     * @response 200 {"data":{"id":1,"status":"pending","total":"49.98","notes":null,"items":[{"id":1,"product_name":"Widget","quantity":2,"unit_price":"24.99","line_total":"49.98"}],"created_at":"2026-06-23T00:00:00.000000Z","updated_at":"2026-06-23T00:00:00.000000Z"}}
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"This action is unauthorized."}
     * @response 404 {"message":"Resource not found."}
     */
    public function show(Order $order): OrderResource
    {
        $this->authorize('view', $order);

        return new OrderResource($order->load(['items', 'payments']));
    }

    /**
     * Update an order.
     *
     * Updates the notes and/or replaces the line items. When items are provided
     * the total is recomputed server-side.
     *
     * @authenticated
     *
     * @urlParam order int required The order ID. Example: 1
     *
     * @bodyParam notes string An optional note for the order. Example: Updated note.
     * @bodyParam items object[] Optional replacement line items. Example: [{"product_name":"Gadget","quantity":1,"unit_price":10}]
     * @bodyParam items[].product_name string required The product name. Example: Gadget
     * @bodyParam items[].quantity int required The quantity (min 1). Example: 1
     * @bodyParam items[].unit_price number required The unit price (min 0). Example: 10
     *
     * @response 200 {"data":{"id":1,"status":"pending","total":"10.00","notes":"Updated note.","items":[{"id":2,"product_name":"Gadget","quantity":1,"unit_price":"10.00","line_total":"10.00"}],"created_at":"2026-06-23T00:00:00.000000Z","updated_at":"2026-06-23T00:00:00.000000Z"}}
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"This action is unauthorized."}
     * @response 422 {"message":"At least one order item is required when updating items.","errors":{"items":["At least one order item is required when updating items."]}}
     */
    public function update(UpdateOrderRequest $request, Order $order, UpdateOrderAction $updateOrder): OrderResource
    {
        $this->authorize('update', $order);

        return new OrderResource($updateOrder->execute($order, $request->validated()));
    }

    /**
     * Delete an order.
     *
     * Permanently removes an order. Orders that already have payments cannot be
     * deleted.
     *
     * @authenticated
     *
     * @urlParam order int required The order ID. Example: 1
     *
     * @response 204 {}
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"This action is unauthorized."}
     * @response 409 {"message":"Order cannot be deleted because it has associated payments."}
     */
    public function destroy(Order $order, DeleteOrderAction $deleteOrder): Response
    {
        $this->authorize('delete', $order);

        $deleteOrder->execute($order);

        return response()->noContent();
    }
}
