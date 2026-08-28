<?php

namespace App\Workflows\Actions;

use App\Enums\Integrations\IntegrationCapability;
use App\Integrations\Contracts\SendsSms;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Messages\SmsMessage;
use App\Workflows\Contracts\WorkflowActionHandler;
use App\Workflows\WorkflowContext;
use RuntimeException;

/**
 * Text the person this workflow is about.
 *
 * Capability-routed exactly as `SendEmailAction` is, and — unlike email — NOT
 * offered at all until an install configures an SMS provider. There is no local
 * fallback for texting: this app has no transport of its own, and an action that
 * appears in the palette while nothing can deliver it is a funnel step that
 * silently never happens.
 *
 * Config: {"integration": "twilio-main", "to": "phone", "body": "..."}
 */
class SendSmsAction implements WorkflowActionHandler
{
    public function __construct(private readonly IntegrationRegistry $integrations) {}

    public function handle(WorkflowContext $context, array $config): array
    {
        $instance = CapabilityRouting::resolve(
            $this->integrations,
            IntegrationCapability::Sms,
            $config['integration'] ?? null,
            'SMS',
        );

        $driver = $this->integrations->driverFor($instance);

        if (! $driver instanceof SendsSms) {
            throw new RuntimeException("[{$instance->name}] cannot send SMS.");
        }

        $to = $context->get($config['to'] ?? 'phone');

        if (! is_string($to) || $to === '') {
            throw new RuntimeException('This step has no phone number to send to.');
        }

        $result = $driver->sendSms($instance, new SmsMessage(
            to: $to,
            body: (string) ($config['body'] ?? ''),
        ));

        return ['integration' => $instance->slug] + $result;
    }
}
