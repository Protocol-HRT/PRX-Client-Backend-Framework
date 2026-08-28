<?php

namespace App\Workflows\Actions;

use App\Enums\Integrations\IntegrationCapability;
use App\Integrations\IntegrationRegistry;
use App\Models\Integrations\IntegrationInstance;
use RuntimeException;

/**
 * Which configured integration should carry out a capability-routed action.
 *
 * Shared by `send_email` and `send_sms` because getting this subtly different in
 * two places is how two channels end up with two behaviours nobody intended.
 *
 * The rules, and why each is what it is:
 *
 *   NAMED       the operator chose; honour it, and fail loudly if it can no
 *               longer do the job rather than quietly falling back to another
 *               instance — silently changing which account sent a message is
 *               worse than not sending it.
 *   EXACTLY ONE use it. Making somebody pick from a list of one is friction with
 *               no decision in it, and it is what makes these actions work on a
 *               fresh install with nothing configured.
 *   SEVERAL     refuse. Picking "the first" works right up until the operator
 *               adds a second provider, at which point every existing workflow
 *               may silently change sender, and nothing in the log would say so.
 *   NONE        refuse, naming the capability, so the message is "configure an
 *               SMS provider" rather than "something went wrong".
 */
class CapabilityRouting
{
    public static function resolve(
        IntegrationRegistry $registry,
        IntegrationCapability $capability,
        mixed $configured,
        string $noun,
    ): IntegrationInstance {
        $available = $registry->instancesOffering($capability);

        if (is_string($configured) && $configured !== '') {
            $chosen = $available->firstWhere('slug', $configured);

            if ($chosen === null) {
                throw new RuntimeException(
                    "The {$noun} integration [{$configured}] is missing, switched off, or no longer "
                    ."enabled for {$capability->label()}."
                );
            }

            return $chosen;
        }

        if ($available->isEmpty()) {
            throw new RuntimeException(
                "No integration on this installation is enabled to send {$noun}. Configure one under "
                .'Integrations first.'
            );
        }

        if ($available->count() > 1) {
            throw new RuntimeException(
                "More than one integration can send {$noun} on this installation, so this step has to "
                .'name the one it means.'
            );
        }

        return $available->first();
    }
}
