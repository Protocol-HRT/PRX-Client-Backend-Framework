<?php

namespace App\Integrations\Contracts;

use App\Integrations\Messages\EmailMessage;
use App\Models\Integrations\IntegrationInstance;

/**
 * Sends email as marketing — a campaign, a broadcast, a nurture step.
 *
 * Distinct from `SendsTransactionalEmail` because consent is distinct. A lead
 * who gave you an address to receive their protocol has not thereby joined a
 * mailing list, and the two are governed by different rules in most
 * jurisdictions. Keeping them apart at the interface means an operator's
 * capability toggles can say which one this account is for.
 *
 * @return array<string, mixed>
 */
interface SendsMarketingEmail extends IntegrationDriver
{
    public function sendMarketingEmail(IntegrationInstance $instance, EmailMessage $message): array;
}
