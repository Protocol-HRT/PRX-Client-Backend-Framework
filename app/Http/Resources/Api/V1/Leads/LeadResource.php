<?php

namespace App\Http\Resources\Api\V1\Leads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public-safe lead representation.
 *
 * Omits internal fields (prescribe_rx_*, notes, ip_address, user_agent)
 * so the frontend can display order confirmation / pre-fill forms without
 * exposing integration internals or PII it doesn't need.
 */
class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender,
            'address' => [
                'line1' => $this->address_line1,
                'line2' => $this->address_line2,
                'city' => $this->city,
                'state' => $this->state,
                'postal_code' => $this->postal_code,
                'country' => $this->country,
            ],
            'consents' => [
                'sms' => $this->sms_consent,
                'email' => $this->email_consent,
                'given_at' => $this->consent_given_at?->toISOString(),
            ],
            'checkout_path' => $this->checkout_path?->value,
            // Absolute URL of the server-rendered PRX embed handoff page.
            // The frontend redirects here after lead creation when the
            // configured checkout path is 'prx'.
            'handoff_url' => route('checkout.handoff', $this->resource),
            'cart_items' => $this->cart_items ?? [],
            'cart_subtotal' => $this->cart_subtotal,
            'handed_off_at' => $this->handed_off_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
        ];
    }
}
