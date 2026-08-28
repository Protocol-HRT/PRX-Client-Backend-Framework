<?php

namespace App\Integrations\Contracts;

use App\Models\Integrations\IntegrationInstance;

/**
 * The base every integration driver implements — and deliberately almost empty.
 *
 * ─── Why capability is an interface and not a supports() method ────────
 *
 * A `supports(string $capability): bool` needs ONE interface wide enough to
 * declare every method any vendor might offer, with each implementation throwing
 * on the half it cannot do. That is not a hypothesis: this codebase already has
 * `TelehealthProviderInterface` at 42 methods, which no second vendor could
 * implement, and the memory of it is why this file is three lines long.
 *
 * With narrow interfaces, a capability is a fact the type system checks. A
 * driver cannot claim `sms` without implementing `SendsSms`, and the registry
 * derives a provider's capability set from `class_implements()` rather than from
 * a declared list that can drift out of step with the code.
 *
 * ─── The case this design exists to survive ────────────────────────────
 *
 * Klaviyo and GoHighLevel are mechanically inverted. Klaviyo has an events API
 * but forbids dropping a contact directly into an automation; GoHighLevel has no
 * events API at all but enrols contacts into workflows by id. Klaviyo groups by
 * list id, GoHighLevel by tag string. Under one interface, each vendor would
 * implement half of it and throw on the rest — which is how a contract stops
 * describing anything. Under these, Klaviyo implements `SyncsContacts` +
 * `TracksEvents`, GoHighLevel implements `SyncsContacts` +
 * `EnrollsInAutomations`, and neither pretends.
 *
 * Build the second driver early rather than last. Two vendors that disagree are
 * what prove a contract; one vendor only proves it can describe itself.
 */
interface IntegrationDriver
{
    /**
     * Prove the stored credentials work, or throw explaining why not.
     *
     * The only thing every driver must be able to do. It exists so an operator
     * can find out at configuration time rather than from a failed run three
     * days later.
     */
    public function test(IntegrationInstance $instance): void;
}
