<?php

namespace App\Actions\Leads;

use App\Models\Lead;
use App\Models\LeadConsent;
use Illuminate\Support\Carbon;

/**
 * Write one immutable consent record, and keep the lead's summary in step.
 *
 * TWO REPRESENTATIONS ON PURPOSE. `leads.email_consent` / `sms_consent` are the
 * current-state read every query and export already depends on; `lead_consents`
 * is the history that can answer "what exactly did they agree to, and from
 * where". Keeping them in sync here — in the one place consent is written — is
 * what stops them drifting into disagreement, which is the failure mode that
 * makes both useless.
 *
 * The audit row is the source of truth. The booleans are a cache of its latest
 * value per channel.
 */
class RecordConsentAction
{
    /**
     * @param  string  $channel  'email' | 'sms'
     * @param  string|null  $text  The sentence the human actually saw. Null only when
     *                             genuinely unknown — never a reconstruction.
     * @param  int|null  $userId  Set when an OPERATOR recorded this rather than the
     *                            visitor, so the two are never confusable.
     */
    public function execute(
        Lead $lead,
        string $channel,
        bool $granted,
        ?string $text = null,
        ?string $version = null,
        string $source = 'api',
        ?string $ip = null,
        ?string $userAgent = null,
        ?int $userId = null,
        ?Carbon $at = null,
    ): LeadConsent {
        $at ??= now();

        $consent = LeadConsent::create([
            'lead_id' => $lead->getKey(),
            'channel' => $channel,
            'granted' => $granted,
            'consent_text' => $text,
            'consent_version' => $version,
            'source' => $source,
            'ip_address' => $ip,
            'user_agent' => $userAgent === null ? null : substr($userAgent, 0, 512),
            'recorded_by_user_id' => $userId,
            'consented_at' => $at,
        ]);

        $this->syncSummary($lead, $channel, $granted, $at);

        return $consent;
    }

    /**
     * Mirror the decision onto the lead's boolean columns.
     *
     * `consent_given_at` is stamped only when something is granted, and is NOT
     * cleared on withdrawal: it records when consent was first obtained, and a
     * withdrawal does not un-happen that. The withdrawal itself is a row in the
     * audit with its own timestamp, which is the honest place for it.
     */
    private function syncSummary(Lead $lead, string $channel, bool $granted, Carbon $at): void
    {
        $column = match ($channel) {
            'email' => 'email_consent',
            'sms' => 'sms_consent',
            // A channel this install invented needs no summary column; the audit
            // row is still written, which is the part that matters.
            default => null,
        };

        $changes = [];

        if ($column !== null && (bool) $lead->{$column} !== $granted) {
            $changes[$column] = $granted;
        }

        if ($granted && $lead->consent_given_at === null) {
            $changes['consent_given_at'] = $at;
        }

        if ($changes !== []) {
            $lead->forceFill($changes)->save();
        }
    }
}
