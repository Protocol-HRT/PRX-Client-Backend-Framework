<?php

namespace App\Actions\Leads;

use App\Actions\Concerns\Transacts;
use App\Data\Leads\LeadData;
use App\Enums\LeadStatus;
use App\Models\Lead;
use Spatie\LaravelData\DataCollection;

class CreateLeadAction
{
    use Transacts;

    public function execute(LeadData $data): Lead
    {
        return $this->tx(function () use ($data) {
            return Lead::create([
                'status' => LeadStatus::New_,
                'first_name' => $data->first_name,
                'last_name' => $data->last_name,
                'email' => $data->email,
                'phone' => $data->phone,
                'date_of_birth' => $data->date_of_birth,
                'age' => $data->age,
                'gender' => $data->gender,
                'address_line1' => $data->address_line1,
                'address_line2' => $data->address_line2,
                'city' => $data->city,
                'state' => $data->state,
                'postal_code' => $data->postal_code,
                'country' => $data->country,
                'sms_consent' => $data->sms_consent,
                'email_consent' => $data->email_consent,
                'consent_given_at' => ($data->sms_consent || $data->email_consent) ? now() : null,
                'cart_items' => $this->serializeCartItems($data),
                'cart_subtotal' => $data->cart_subtotal,
                'checkout_path' => $data->checkout_path,
                'utm_source' => $data->utm_source,
                'utm_medium' => $data->utm_medium,
                'utm_campaign' => $data->utm_campaign,
                'utm_term' => $data->utm_term,
                'utm_content' => $data->utm_content,
                'referrer' => $data->referrer,
                'landing_url' => $data->landing_url,
                'user_agent' => $data->user_agent,
                'ip_address' => $data->ip_address,
                'notes' => $data->notes,
                'cart_ulid' => $data->cart_ulid,
                'quiz_answers' => $data->quiz_answers,
                'quiz_id' => $data->quiz_id,
                // Stamped only for a lead that actually came through the quiz,
                // so it doubles as the flag separating funnel leads from cart
                // leads without anyone inspecting the JSON.
                'quiz_completed_at' => $data->quiz_id !== null ? now() : null,
            ]);
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeCartItems(LeadData $data): array
    {
        if ($data->cart_items instanceof DataCollection) {
            return $data->cart_items->toArray();
        }

        return collect($data->cart_items)
            ->map(fn ($item) => is_array($item) ? $item : $item->toArray())
            ->all();
    }
}
