<?php

namespace App\Integrations\Contracts;

use App\Integrations\Messages\SmsMessage;
use App\Models\Integrations\IntegrationInstance;

/**
 * Sends one text message.
 *
 * Note the asymmetry worth remembering when a driver lands here: some SMS
 * vendors will sign a BAA and most marketing platforms will not, so SMS is one
 * of the few channels where health content may be legitimate. That is still the
 * operator's attestation to make, never this interface's assumption.
 *
 * @return array<string, mixed>
 */
interface SendsSms extends IntegrationDriver
{
    public function sendSms(IntegrationInstance $instance, SmsMessage $message): array;
}
