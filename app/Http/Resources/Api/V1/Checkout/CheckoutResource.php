<?php

namespace App\Http\Resources\Api\V1\Checkout;

use App\Data\Checkout\CheckoutResultData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CheckoutResultData
 *
 * Response shape for POST /api/v1/checkout:
 *
 * {
 *   "data": {
 *     "order_uuid":    "...",
 *     "checkout_path": "prx",
 *     "prescribe_rx": {
 *       "encounter_id":     "...",
 *       "encounter_number": "...",
 *       "patient_id":       "...",
 *       "status":           "pending_intake"
 *     }
 *   }
 * }
 *
 * The frontend uses `checkout_path` to decide what to render next:
 *   - "prx"   → load the PRX embed iframe with encounter_id
 *   - "local" → render local payment form (not yet implemented)
 */
class CheckoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'order_uuid' => $this->order_uuid,
            'checkout_path' => $this->checkout_path,
            'prescribe_rx' => $this->prescribe_rx,
        ];
    }
}
