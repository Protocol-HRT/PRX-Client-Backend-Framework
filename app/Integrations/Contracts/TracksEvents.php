<?php

namespace App\Integrations\Contracts;

use App\Integrations\Messages\ContactPayload;
use App\Models\Integrations\IntegrationInstance;

/**
 * Records that a person did something, for the destination to react to.
 *
 * NOT UNIVERSAL, and that is exactly why it is its own interface. Klaviyo has a
 * full events API and treats it as the primary way to trigger a flow;
 * GoHighLevel's v3 API has no events concept whatsoever — the only `/events` in
 * its whole specification belongs to calendars. A single "notify the CRM"
 * abstraction would therefore be a method one of them could only throw from.
 *
 * @param  array<string, mixed>  $properties
 * @return array<string, mixed>
 */
interface TracksEvents extends IntegrationDriver
{
    public function trackEvent(
        IntegrationInstance $instance,
        ContactPayload $contact,
        string $event,
        array $properties = [],
    ): array;
}
