<?php

namespace App\Integrations\Contracts;

use App\Integrations\Messages\ContactPayload;
use App\Models\Integrations\IntegrationInstance;

/**
 * Creates or updates a person at the far end, and puts them in a group.
 *
 * The one thing every CRM and marketing platform genuinely shares, which is why
 * it is the only contact-shaped interface with two methods rather than five.
 *
 * ─── `group` is a name, and the instance decides what it means ─────────
 *
 * Klaviyo groups by LIST ID; GoHighLevel groups by TAG STRING and has no lists
 * at all. Rather than invent a lowest common denominator that is wrong for both,
 * the caller passes a semantic name and the instance's `settings` map it — a
 * Klaviyo driver looks the name up to a list id, a GoHighLevel driver passes it
 * through as a tag. The workflow author writes "quiz-completers" once and it
 * means the right thing at each destination.
 *
 * ─── A group NAME can disclose health status ──────────────────────────
 *
 * "TRT interest" tells a destination as much as a symptom answer does, and so
 * does a recommended product name. Whatever classifies field VALUES has to
 * classify these too; a driver is the wrong place to be deciding it.
 *
 * ─── `addToGroup` takes the whole contact, and that is load-bearing ────
 *
 * A remote id is enough to put somebody ON a list and not enough to SUBSCRIBE
 * them. Consent is per channel, so the verb depends both on which channels were
 * granted and on the identifier that channel needs — an email consent means
 * nothing without an email address. Both live on the payload, which is why the
 * payload travels rather than the id alone.
 *
 * A driver may ignore consent where its platform has no such concept. What it
 * may NOT do is look for consent in `$contact->attributes`: those are
 * operator-mapped values, and consent is not one of them.
 */
interface SyncsContacts extends IntegrationDriver
{
    /** @return string the destination's own id for this person. */
    public function upsertContact(IntegrationInstance $instance, ContactPayload $contact): string;

    public function addToGroup(
        IntegrationInstance $instance,
        string $remoteId,
        string $group,
        ContactPayload $contact,
    ): void;
}
