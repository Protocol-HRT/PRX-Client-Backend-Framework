<?php

namespace App\Integrations\Contracts;

use App\Integrations\Messages\EmailMessage;
use App\Models\Integrations\IntegrationInstance;

/**
 * Sends one email to one person, triggered by something they did.
 *
 * SEPARATE FROM MARKETING EMAIL because the authorisation is separate. A vendor
 * account cleared for password resets and order confirmations is not thereby
 * cleared to send a campaign, and several vendors price and police the two
 * differently. An operator who can only tick one should only get one.
 *
 * @return array<string, mixed> whatever identifies the send at the far end —
 *                              a message id and a status, never the body.
 */
interface SendsTransactionalEmail extends IntegrationDriver
{
    public function sendEmail(IntegrationInstance $instance, EmailMessage $message): array;
}
