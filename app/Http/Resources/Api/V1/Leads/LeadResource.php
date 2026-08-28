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
            // The visitor's own answers, behind the same opaque UUID that
            // gates the rest of this resource. The plan page reads them to
            // show what was asked and what it concluded.
            'age' => $this->age,
            'quiz' => $this->when($this->quiz_id !== null, fn (): array => [
                'answers' => $this->quiz_answers ?? [],
                'completed_at' => $this->quiz_completed_at?->toIso8601String(),
                // A FACT, not an intention. The plan page says "we've sent a
                // copy" only when this is set, so a failed or disabled send
                // shows honest copy instead of a confident lie.
                'plan_sent_at' => $this->plan_sent_at?->toIso8601String(),
            ]),
            'cart_items' => $this->cart_items ?? [],
            'cart_subtotal' => $this->cart_subtotal,
            'handed_off_at' => $this->handed_off_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
        ];
    }
}
