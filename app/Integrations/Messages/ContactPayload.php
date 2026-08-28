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
    ) {}
}
