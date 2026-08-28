<?php

namespace App\Workflows\Actions;

use App\Enums\Integrations\IntegrationCapability;
use App\Integrations\Contracts\SendsTransactionalEmail;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Messages\EmailMessage;
use App\Workflows\Contracts\WorkflowActionHandler;
use App\Workflows\WorkflowContext;
use RuntimeException;

/**
 * Email the person this workflow is about.
 *
 * CAPABILITY-ROUTED, NOT VENDOR-ROUTED. The action does not know or care who
 * sends the message; it asks for an integration offering `transactional_email`
 * and uses it. An install that has configured nothing still has one — this
 * site's own mail stack registers as a provider like any other — so the action
 * works on day one, and pointing it at a vendor later is a settings change
 * rather than a different action.
 *
 * If the operator named an instance, that one is used. If they did not and
 * exactly one is available, it is used, because making somebody choose from a
 * list of one is friction with no decision in it. If several are available the
 * choice is genuinely theirs and leaving it unmade is an error, not something to
 * guess at — picking "the first" would work until the day they add a second and
 * the funnel silently changes sender.
 *
 * Config: {"integration": "site-mail", "to": "email", "subject": "...", "body": "..."}
 */
class SendEmailAction implements WorkflowActionHandler
{
    public function __construct(private readonly IntegrationRegistry $integrations) {}

    public function handle(WorkflowContext $context, array $config): array
    {
        $instance = CapabilityRouting::resolve(
            $this->integrations,
            IntegrationCapability::TransactionalEmail,
            $config['integration'] ?? null,
            'email',
        );

        $driver = $this->integrations->driverFor($instance);

        if (! $driver instanceof SendsTransactionalEmail) {
            throw new RuntimeException("[{$instance->name}] cannot send transactional email.");
        }

        // The recipient is read through the bounded accessor, so a workflow
        // cannot be pointed at an unregistered column to harvest an address.
        $to = $context->get($config['to'] ?? 'email');

        if (! is_string($to) || $to === '') {
            throw new RuntimeException('This step has no email address to send to.');
        }

        $result = $driver->sendEmail($instance, new EmailMessage(
            to: $to,
            subject: (string) ($config['subject'] ?? ''),
            body: (string) ($config['body'] ?? ''),
            toName: $this->name($context),
        ));

        return ['integration' => $instance->slug] + $result;
    }

    private function name(WorkflowContext $context): ?string
    {
        $first = $context->get('first_name');

        return is_string($first) && $first !== '' ? $first : null;
    }
}
