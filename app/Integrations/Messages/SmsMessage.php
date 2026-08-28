<?php

namespace App\Integrations\Messages;

/**
 * One text message.
 *
 * `from` is nullable because most instances configure a single sending number in
 * `settings` and never vary it; passing one per message is the exception.
 */
readonly class SmsMessage
{
    public function __construct(
        public string $to,
        public string $body,
        public ?string $from = null,
    ) {}
}
