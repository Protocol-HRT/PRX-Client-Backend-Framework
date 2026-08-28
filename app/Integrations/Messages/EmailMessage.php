<?php

namespace App\Integrations\Messages;

/**
 * One email, in the only terms every provider agrees on.
 *
 * A value object rather than an array so a driver cannot quietly depend on a key
 * that another caller does not set. Deliberately minimal: anything a specific
 * vendor needs beyond this belongs in that instance's `settings`, not in a
 * shared shape every other driver has to ignore.
 */
readonly class EmailMessage
{
    /**
     * @param  array<string, mixed>  $context  Merge data for a templated send.
     */
    public function __construct(
        public string $to,
        public string $subject,
        public string $body,
        public ?string $toName = null,
        public ?string $template = null,
        public array $context = [],
    ) {}
}
