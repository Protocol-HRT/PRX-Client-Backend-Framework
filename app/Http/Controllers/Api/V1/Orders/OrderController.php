<?php

namespace App\Http\Controllers\Api\V1\Orders;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\V1\Orders\OrderResource;
use App\Models\Commerce\Order;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/orders/{uuid}
 *
 * Orders are retrieved by opaque UUID — the UUID is only returned at
 * checkout completion so treating it as a bearer credential is safe.
 */
class OrderController extends ApiController
{
    /**
     * Retrieve an order by UUID.
     *
     * Returns an order with its items, shipments, and fulfillment center. The UUID is
     * returned only at checkout completion and serves as a bearer credential for this
     * endpoint. Requires a valid Sanctum Bearer token.
     *
     * @tags Orders
     */
    public function show(string $uuid): OrderResource|JsonResponse
    {
        $order = Order::where('uuid', $uuid)
            ->with(['items', 'shipments', 'fulfillmentCenter'])
            ->first();

        if (! $order) {
            return $this->error('Order not found.', 404);
        }

        return new OrderResource($order);
    }
}
