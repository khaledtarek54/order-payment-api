<?php

declare(strict_types=1);

namespace App\Modules\Order\Http\Controllers;

use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Http\Requests\UpdateOrderStatusRequest;
use App\Modules\Order\Http\Resources\OrderResource;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Services\OrderService;
use App\Support\Http\Controllers\ApiController;

/**
 * @group Orders
 *
 * Transition an order between its lifecycle statuses.
 */
class OrderStatusController extends ApiController
{
    public function __construct(private readonly OrderService $service) {}

    /**
     * Change an order's status.
     *
     * Transitions the order to the target status. Invalid transitions (for
     * example confirming a cancelled order) are rejected.
     *
     * @authenticated
     *
     * @urlParam order int required The order ID. Example: 1
     *
     * @bodyParam status string required The target status (pending, confirmed, cancelled). Example: confirmed
     *
     * @response 200 {"data":{"id":1,"status":"confirmed","total":"49.98","notes":null,"created_at":"2026-06-23T00:00:00.000000Z","updated_at":"2026-06-23T00:00:00.000000Z"}}
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"This action is unauthorized."}
     * @response 422 {"message":"Cannot change order status from 'cancelled' to 'confirmed'."}
     */
    public function update(UpdateOrderStatusRequest $request, Order $order): OrderResource
    {
        $this->authorize('update', $order);

        $order = $this->service->changeStatus(
            $order,
            OrderStatus::from($request->validated()['status']),
        );

        return new OrderResource($order);
    }
}
