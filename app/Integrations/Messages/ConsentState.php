<?php

namespace App\Integrations\Messages;

/**
 * Which channels this person has actually consented to marketing on.
 *
 * ─── Why this rides on the payload and not through the field mapper ────
 *
 * Everything else a destination receives is something an operator CHOSE to map.
 * Consent is not: it is an invariant, and a mapping an operator can point
 * anywhere is the wrong shape for it. Put `consent` in the mapper and somebody
 * eventually maps a quiz answer to it, or maps nothing and the destination
 * quietly reads "not consented" for a subscriber who did consent. So it is
 * resolved by the action, carried here, and read only by drivers.
 *
 * ─── Absent means NOT consented, never "unknown, proceed" ──────────────
 *
 * `null` on `ContactPayload::consent` and an empty state here mean the same
 * thing at every call site: no channel is granted. A workflow whose subject is
 * not a person cannot have a consent record, and the safe reading of that is
 * silence, not permission.
 *
 * The vocabulary is `lead_consents.channel` — `'email'`, `'sms'`, and whatever
 * an install adds later. Deliberately generic: no vendor names a channel, and a
 * driver translates it to its own consent model (Klaviyo subscriptions,
 * GoHighLevel DND) the same way it translates a group name to a list id.
 */
readonly class ConsentState
{
    /** @param  list<string>  $granted  channel names with a live grant */
    private function __construct(public array $granted) {}

    /** @param  list<string>  $channels */
    public static function forChannels(array $channels): self
    {
        return new self(array_values(array_unique($channels)));
    }

    /** No channel granted — also what an unknown or non-person subject gets. */
    public static function none(): self
    {
        return new self([]);
    }

    public function grants(string $channel): bool
    {
        return in_array($channel, $this->granted, true);
    }

    public function grantsAnything(): bool
    {
        return $this->granted !== [];
    }
}
