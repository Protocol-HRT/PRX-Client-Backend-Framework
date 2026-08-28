<?php

namespace App\Integrations\Drivers;

use App\Integrations\Contracts\EnrollsInAutomations;
use App\Integrations\Contracts\SyncsContacts;
use App\Integrations\Messages\ContactPayload;
use App\Integrations\Support\TalksToVendorApi;
use App\Models\Integrations\IntegrationInstance;
use RuntimeException;

/**
 * GoHighLevel — contacts, tags and workflow enrolment.
 *
 * ─── The mirror image of Klaviyo, and that is why it exists ────────────
 *
 * `SyncsContacts` + `EnrollsInAutomations`, and **not** `TracksEvents`. There is
 * no events API to implement: the only `/events` in GoHighLevel's v2 surface
 * belongs to calendars. Klaviyo is the exact inverse — events yes, enrolment
 * forbidden.
 *
 * Two vendors that disagree this sharply are the reason capabilities are
 * separate interfaces rather than one contract with a `supports()` method. Under
 * one interface each of these classes would implement half of it and throw from
 * the rest, which is how `TelehealthProviderInterface` reached 42 methods that
 * no second vendor could satisfy. Here, each declares only what it can do and
 * the action palette follows automatically.
 *
 * ─── Groups are TAG STRINGS, not ids ───────────────────────────────────
 *
 * GoHighLevel has no lists. `addToGroup('quiz-completers')` sends the string
 * through as a tag, so the same workflow step that resolves to a list id at
 * Klaviyo needs no mapping here. That asymmetry is the point of `addToGroup()`
 * taking a NAME: the workflow author writes one thing and each driver means what
 * its vendor means.
 *
 * ─── Things that will bite whoever extends this ────────────────────────
 *
 *  * Every call is scoped to a LOCATION (sub-account). The location id is
 *    configuration, not a credential — it identifies which account, not who may
 *    reach it — so it lives in settings and appears in the admin unmasked.
 *  * Enrolment starts a contact at the TOP of a workflow. Neither this vendor nor
 *    Klaviyo can start somebody mid-sequence, so "where in the funnel they
 *    arrive" has to become *which* workflow plus contact fields its internal
 *    branches read.
 *  * The `Version` header is required and dated. It is effectively the API
 *    version; changing it can change response shapes.
 *
 * UNVERIFIED: built against GoHighLevel's documented v2 API, not against a live
 * account. Endpoint paths, the upsert response shape and the enrolment body have
 * never been exercised against the real service.
 */
class GoHighLevelDriver implements EnrollsInAutomations, SyncsContacts
{
    use TalksToVendorApi;

    private const BASE = 'https://services.leadconnectorhq.com';

    private const VERSION = '2021-07-28';

    public function test(IntegrationInstance $instance): void
    {
        $locationId = $this->locationId($instance);

        $this->ok(
            $this->request($instance)->get(self::BASE."/locations/{$locationId}"),
            'Connecting to GoHighLevel',
        );
    }

    public function upsertContact(IntegrationInstance $instance, ContactPayload $contact): string
    {
        if ($contact->email === null && $contact->phone === null) {
            throw new RuntimeException('GoHighLevel needs an email address or a phone number to identify someone.');
        }

        $payload = array_filter([
            'locationId' => $this->locationId($instance),
            'email' => $contact->email,
            'phone' => $contact->phone,
            'firstName' => $contact->firstName,
            'lastName' => $contact->lastName,
            'source' => $instance->settings['source'] ?? null,
        ], fn ($value): bool => $value !== null);

        // Everything the operator mapped that is not one of the standard fields
        // becomes a custom field. GoHighLevel takes these as a keyed list rather
        // than a flat object.
        $custom = $this->customFields($contact);

        if ($custom !== []) {
            $payload['customFields'] = $custom;
        }

        $response = $this->ok(
            $this->request($instance)->post(self::BASE.'/contacts/upsert', $payload),
            'Sending a contact to GoHighLevel',
        );

        // The upsert response has nested the contact under different keys across
        // revisions, so this reads the documented shape and falls back rather
        // than exploding on a wrapper change.
        $id = $response->json('contact.id') ?? $response->json('id');

        if (! is_string($id) || $id === '') {
            throw new RuntimeException('GoHighLevel accepted the contact but returned no id.');
        }

        return $id;
    }

    /**
     * A tag carries no consent, so `$contact` is unused here and that is correct.
     *
     * GoHighLevel has no subscribe verb to branch to: tagging a contact says
     * nothing about what they agreed to, and its opt-out lives in a separate DND
     * concept on the contact rather than on the tag. So consent is enforced
     * upstream — the action skips the group entirely when nothing was granted —
     * and this method has nothing left to decide.
     */
    public function addToGroup(
        IntegrationInstance $instance,
        string $remoteId,
        string $group,
        ContactPayload $contact,
    ): void {
        $this->ok(
            $this->request($instance)->post(self::BASE."/contacts/{$remoteId}/tags", [
                'tags' => [$group],
            ]),
            "Tagging a GoHighLevel contact with [{$group}]",
        );
    }

    public function enroll(IntegrationInstance $instance, string $remoteId, string $automation): void
    {
        $this->ok(
            $this->request($instance)->post(self::BASE."/contacts/{$remoteId}/workflow/{$automation}", [
                // Required by the endpoint. "Now" is the only sensible default;
                // scheduling a start is a feature nobody has asked for, and
                // guessing a delay would be worse than not offering one.
                'eventStartTime' => now()->toIso8601String(),
            ]),
            "Starting the GoHighLevel workflow [{$automation}]",
        );
    }

    /** @return list<array{key: string, field_value: mixed}> */
    private function customFields(ContactPayload $contact): array
    {
        $standard = ['email', 'phone', 'first_name', 'last_name'];
        $fields = [];

        foreach ($contact->attributes as $key => $value) {
            if (in_array($key, $standard, true) || $value === null) {
                continue;
            }

            $fields[] = ['key' => $key, 'field_value' => $value];
        }

        return $fields;
    }

    private function locationId(IntegrationInstance $instance): string
    {
        $id = $instance->settings['location_id'] ?? null;

        if (! is_string($id) || trim($id) === '') {
            throw new RuntimeException(
                'This GoHighLevel integration has no Location ID. Every call is scoped to a '
                .'sub-account, so it cannot work without one.'
            );
        }

        return trim($id);
    }

    private function request(IntegrationInstance $instance)
    {
        $token = $this->credential($instance->credentials ?? [], 'access_token', 'GoHighLevel API token');

        return $this->http()->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Version' => self::VERSION,
            'Accept' => 'application/json',
        ]);
    }
}
