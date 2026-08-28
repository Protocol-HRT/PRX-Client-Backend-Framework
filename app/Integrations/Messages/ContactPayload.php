<?php

namespace App\Integrations\Messages;

/**
 * A person as a destination needs to see them.
 *
 * ─── `attributes` is what the field mapper fills, and it is the risk ───
 *
 * The named properties are identity. Everything else an operator chose to send
 * arrives in `attributes`, which is exactly where a health answer would land if
 * nothing stopped it — so this is the payload the PHI check has to run against
 * before a driver ever sees it, not after.
 *
 * `externalId` is our own lead identifier. Worth knowing before designing around
 * it: at least one major platform does not use a supplied external id when
 * merging profiles, so keying on ours can create duplicates there rather than
 * preventing them. Treat it as a back-reference for our own reconciliation, not
 * as a merge key.
 *
 * ─── `consent` is NOT one of the mapped fields, deliberately ───────────
 *
 * It arrives beside `attributes` rather than inside it because it is an
 * invariant, not a choice: the action resolves it from our own consent audit,
 * and an operator can neither map it, rename it, nor point it at something else.
 * A driver reads it to choose its verb — subscribe versus merely add — and must
 * never look for consent among `attributes`, where a mapping called "consent"
 * is nothing but a custom property somebody typed.
 *
 * `null` means not consented. See `ConsentState`.
 *
 * @param  array<string, scalar|null>  $attributes
 */
readonly class ContactPayload
{
    public function __construct(
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $externalId = null,
        public array $attributes = [],
        public ?ConsentState $consent = null,
    ) {}

    /** Convenience for drivers: an absent state and an empty one read the same. */
    public function consents(string $channel): bool
    {
        return $this->consent?->grants($channel) ?? false;
    }
}
