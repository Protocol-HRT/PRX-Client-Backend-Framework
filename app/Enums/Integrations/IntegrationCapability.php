<?php

namespace App\Enums\Integrations;

use App\Integrations\Contracts\IntegrationDriver;
use App\Integrations\Contracts\SendsMarketingEmail;
use App\Integrations\Contracts\SendsSms;
use App\Integrations\Contracts\SendsTransactionalEmail;
use App\Integrations\Contracts\SyncsContacts;

/**
 * What an integration is for — the vocabulary the action palette filters on.
 *
 * ─── Two different questions, and both have to be asked ────────────────
 *
 * A capability is offered only when BOTH are true:
 *
 *   CAN it?     the driver implements this case's `contract()`. A fact about
 *               code, checked with `instanceof`, impossible to misdeclare.
 *   MAY it?     the operator ticked it on the instance. A fact about their
 *               account — one Twilio account may be authorised for SMS but not
 *               voice, one email vendor for transactional but not marketing.
 *
 * Neither implies the other, which is why the toggles live on the instance and
 * the contracts live in code. `capabilitiesOf()` answers the first; the
 * instance's `capabilities` column answers the second; the palette intersects
 * them.
 *
 * ─── Why transactional and marketing email are separate cases ──────────
 *
 * Because the CONSENT is separate. Somebody who handed over an address to
 * receive their own protocol has not joined a mailing list, and in most
 * jurisdictions the two are governed differently. Collapsing them into one
 * "email" capability would make that distinction unexpressable at exactly the
 * layer that decides where data goes.
 */
enum IntegrationCapability: string
{
    case Sms = 'sms';
    case TransactionalEmail = 'transactional_email';
    case MarketingEmail = 'marketing_email';
    case Crm = 'crm';

    public function label(): string
    {
        return match ($this) {
            self::Sms => 'SMS',
            self::TransactionalEmail => 'Transactional email',
            self::MarketingEmail => 'Marketing email',
            self::Crm => 'CRM / contact sync',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Sms => 'Text messages triggered by something a person did.',
            self::TransactionalEmail => 'One-to-one email — a confirmation, a plan, a password reset.',
            self::MarketingEmail => 'Campaigns and nurture sequences. Needs marketing consent, which is not the same as having someone\'s address.',
            self::Crm => 'Push people and their attributes to a CRM or marketing platform.',
        };
    }

    /**
     * The interface a driver must implement to be capable of this.
     *
     * @return class-string<IntegrationDriver>
     */
    public function contract(): string
    {
        return match ($this) {
            self::Sms => SendsSms::class,
            self::TransactionalEmail => SendsTransactionalEmail::class,
            self::MarketingEmail => SendsMarketingEmail::class,
            self::Crm => SyncsContacts::class,
        };
    }

    /**
     * Everything a given driver class is capable of, derived from the code.
     *
     * DERIVED, NEVER DECLARED. A provider that listed its own capabilities in a
     * registration array would eventually list one it no longer implements, and
     * the failure would surface as a run-time error in an operator's funnel
     * rather than as a type error here.
     *
     * @param  class-string  $driver
     * @return list<self>
     */
    public static function capabilitiesOf(string $driver): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $capability): bool => is_a($driver, $capability->contract(), true),
        ));
    }

    /** @return array<string, string> value => label, for a Filament checkbox list. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
