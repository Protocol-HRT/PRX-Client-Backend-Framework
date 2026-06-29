<?php

namespace App\Http\Controllers\Api\V1\Leads;

use App\Actions\Leads\CreateLeadAction;
use App\Data\Leads\LeadData;
use App\Enums\CheckoutPath;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\V1\Leads\LeadResource;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/v1/leads        Create a lead at checkout initiation.
 * GET  /api/v1/leads/{uuid} Retrieve a lead by UUID (for pre-fill on return visit).
 */
class LeadController extends ApiController
{
    /**
     * Create a new lead.
     *
     * Called when the user submits the first checkout step (name + email + cart).
     * The cart snapshot is passed in from the frontend's live cart state so the
     * backend Lead record captures what was selected at lead-capture time. Binds
     * the lead to the X-Cart-Token session when present.
     *
     * @tags Leads
     *
     * @unauthenticated
     */
    public function store(Request $request, CreateLeadAction $action): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date', 'before:-18 years'],
            'gender' => ['nullable', 'string', 'in:male,female,other,prefer_not_to_say'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:8'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'country' => ['nullable', 'string', 'size:2'],
            'sms_consent' => ['boolean'],
            'email_consent' => ['boolean'],
            'checkout_path' => ['nullable', 'string', 'in:local,prx'],
            'cart_items' => ['nullable', 'array'],
            'cart_subtotal' => ['nullable', 'numeric', 'min:0'],
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'utm_term' => ['nullable', 'string', 'max:255'],
            'utm_content' => ['nullable', 'string', 'max:255'],
            'referrer' => ['nullable', 'url', 'max:2048'],
            'landing_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $data = new LeadData(
            first_name: $validated['first_name'],
            last_name: $validated['last_name'],
            email: $validated['email'],
            phone: $validated['phone'] ?? null,
            date_of_birth: $validated['date_of_birth'] ?? null,
            gender: $validated['gender'] ?? null,
            address_line1: $validated['address_line1'] ?? null,
            address_line2: $validated['address_line2'] ?? null,
            city: $validated['city'] ?? null,
            state: $validated['state'] ?? null,
            postal_code: $validated['postal_code'] ?? null,
            country: $validated['country'] ?? 'US',
            sms_consent: (bool) ($validated['sms_consent'] ?? false),
            email_consent: (bool) ($validated['email_consent'] ?? false),
            cart_items: $validated['cart_items'] ?? [],
            cart_subtotal: isset($validated['cart_subtotal']) ? (float) $validated['cart_subtotal'] : null,
            checkout_path: CheckoutPath::from($validated['checkout_path'] ?? CheckoutPath::PrescribeRx->value),
            utm_source: $validated['utm_source'] ?? null,
            utm_medium: $validated['utm_medium'] ?? null,
            utm_campaign: $validated['utm_campaign'] ?? null,
            utm_term: $validated['utm_term'] ?? null,
            utm_content: $validated['utm_content'] ?? null,
            referrer: $validated['referrer'] ?? null,
            landing_url: $validated['landing_url'] ?? null,
            user_agent: substr((string) $request->userAgent(), 0, 512),
            ip_address: $request->ip(),
            cart_ulid: $request->header('X-Cart-Token') ?: null,
        );

        $lead = $action->execute($data);

        return $this->success((new LeadResource($lead))->toArray($request), status: 201);
    }

    /**
     * Retrieve a lead by UUID.
     *
     * Used by the frontend to pre-fill checkout forms on return visits (e.g. the user
     * closes the tab and returns via a recovery email link). The UUID is treated as a
     * bearer credential — only share it with the lead owner.
     *
     * @tags Leads
     *
     * @unauthenticated
     */
    public function show(Lead $lead): JsonResponse
    {
        return $this->success((new LeadResource($lead))->toArray(request()));
    }
}
