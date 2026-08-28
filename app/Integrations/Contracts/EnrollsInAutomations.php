<?php

namespace App\Integrations\Contracts;

use App\Models\Integrations\IntegrationInstance;

/**
 * Drops a person directly into an automation at the far end.
 *
 * THE MIRROR IMAGE OF `TracksEvents`, and the reason both exist. GoHighLevel
 * supports this directly; Klaviyo's terms forbid it — a Klaviyo profile enters a
 * flow by meeting its trigger, never by being pushed in. So the two vendors
 * accomplish "start the follow-up sequence" through opposite verbs, and a
 * workflow that wants it has to choose the one its destination actually offers.
 *
 * One limit neither vendor escapes, worth knowing before designing a funnel on
 * top of this: you cannot start somebody MID-automation. Klaviyo enters at a
 * trigger, GoHighLevel at the top. "Where in the funnel they arrive" has to
 * become *which* automation, plus contact attributes its internal branches read.
 */
interface EnrollsInAutomations extends IntegrationDriver
{
    public function enroll(IntegrationInstance $instance, string $remoteId, string $automation): void;
}
