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
 */
interface SyncsContacts extends IntegrationDriver
{
    /** @return string the destination's own id for this person. */
    public function upsertContact(IntegrationInstance $instance, ContactPayload $contact): string;

    public function addToGroup(IntegrationInstance $instance, string $remoteId, string $group): void;
}
