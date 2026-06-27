<?php

namespace App\Http\Controllers\Api\V1\Leads;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\V1\Leads\LeadResource;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
     * backend Lead record captures what was selected at lead-capture time.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date', 'before:-18 years'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female', 'other', 'prefer_not_to_say'])],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:8'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'country' => ['nullable', 'string', 'size:2'],
            'sms_consent' => ['boolean'],
            'email_consent' => ['boolean'],
            'checkout_path' => ['nullable', 'string', Rule::in(['local', 'prx'])],
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

        if (($validated['sms_consent'] ?? false) || ($validated['email_consent'] ?? false)) {
            $validated['consent_given_at'] = now();
        }

        $validated['ip_address'] = $request->ip();
        $validated['user_agent'] = substr((string) $request->userAgent(), 0, 512);

        $lead = Lead::create($validated);

        return $this->success((new LeadResource($lead))->toArray($request), status: 201);
    }

    /**
     * Retrieve a lead by UUID.
     *
     * Used by the frontend to pre-fill checkout forms on return visits
     * (e.g. user closes tab and comes back via a recovery email link).
     */
    public function show(Lead $lead): JsonResponse
    {
        return $this->success((new LeadResource($lead))->toArray(request()));
    }
}
