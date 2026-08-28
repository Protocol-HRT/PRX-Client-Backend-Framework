<?php

namespace App\Integrations\Drivers;

use App\Integrations\Contracts\SendsSms;
use App\Integrations\Messages\SmsMessage;
use App\Integrations\Support\TalksToVendorApi;
use App\Models\Integrations\IntegrationInstance;
use RuntimeException;

/**
 * Twilio — SMS.
 *
 * ─── Why this is an instance and not the existing settings page ────────
 *
 * `CommunicationSettings` has carried `twilio_account_sid` / `twilio_auth_token`
 * / `twilio_from_number` for months with nothing that sends. Those stay as they
 * are — they belong to the site's own notification settings — and this driver
 * takes its credentials from the instance row instead, because an install can
 * legitimately have two Twilio accounts (one for clinical notifications, one for
 * marketing) with different numbers and different authorisations. A singleton
 * settings page cannot express that, and the capability toggles are exactly the
 * thing that has to differ between them.
 *
 * ─── The BAA point, stated because it is unusual ───────────────────────
 *
 * Twilio will sign a BAA, which makes SMS one of the few channels where health
 * content can be legitimate for an install that has one. That is still the
 * operator's attestation to make on the instance — this class has no opinion and
 * enforces nothing. `FieldMap` upstream is the only gate.
 *
 * ─── Sender resolution ─────────────────────────────────────────────────
 *
 * A messaging service SID is preferred over a bare number when both are present:
 * it is what carries sender pools, opt-out handling and compliance registration,
 * and an install that has configured one has done so deliberately.
 *
 * UNVERIFIED: built against Twilio's documented REST API, not against a live
 * account. The endpoint, the form encoding and the error shape below have never
 * been exercised against the real service.
 */
class TwilioDriver implements SendsSms
{
    use TalksToVendorApi;

    private const BASE = 'https://api.twilio.com/2010-04-01';

    public function test(IntegrationInstance $instance): void
    {
        [$sid, $token] = $this->auth($instance);

        $this->ok(
            $this->http()->withBasicAuth($sid, $token)->get(self::BASE."/Accounts/{$sid}.json"),
            'Connecting to Twilio',
        );

        // Credentials can be perfect while the account can still send nothing.
        // Better to say so here than to have a workflow discover it.
        $this->sender($instance, null);
    }

    public function sendSms(IntegrationInstance $instance, SmsMessage $message): array
    {
        [$sid, $token] = $this->auth($instance);

        $body = trim($message->body);

        if ($body === '') {
            throw new RuntimeException('Twilio will not send an empty message.');
        }

        $response = $this->ok(
            $this->http()
                ->withBasicAuth($sid, $token)
                // Twilio's REST API is form-encoded, not JSON. Sending JSON gets
                // a 400 that does not explain itself.
                ->asForm()
                ->post(self::BASE."/Accounts/{$sid}/Messages.json", array_merge(
                    ['To' => $message->to, 'Body' => $body],
                    $this->sender($instance, $message->from),
                )),
            'Sending an SMS through Twilio',
        );

        // The message SID and its status, and nothing else. `workflow_action_runs
        // .output` is unencrypted and readable by any admin with run-log access,
        // so the body must never land there.
        return [
            'message_sid' => $response->json('sid'),
            'status' => $response->json('status'),
        ];
    }

    /**
     * Whichever sender this instance is configured to send as.
     *
     * @return array<string, string>
     */
    private function sender(IntegrationInstance $instance, ?string $override): array
    {
        $settings = $instance->settings ?? [];

        if (filled($settings['messaging_service_sid'] ?? null)) {
            return ['MessagingServiceSid' => (string) $settings['messaging_service_sid']];
        }

        $from = $override ?? ($settings['from_number'] ?? null);

        if (! is_string($from) || trim($from) === '') {
            throw new RuntimeException(
                'This Twilio integration has no sender. Set a From number or a Messaging Service SID '
                .'in its options.'
            );
        }

        return ['From' => trim($from)];
    }

    /** @return array{0: string, 1: string} */
    private function auth(IntegrationInstance $instance): array
    {
        $credentials = $instance->credentials ?? [];

        return [
            $this->credential($credentials, 'account_sid', 'Twilio Account SID'),
            $this->credential($credentials, 'auth_token', 'Twilio auth token'),
        ];
    }
}
