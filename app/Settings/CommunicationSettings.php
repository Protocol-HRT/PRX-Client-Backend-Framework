<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Twilio credentials and channel toggles.
 * Auth token is encrypted at rest via the `encrypted()` method.
 * Account SID is not secret (it appears in HTTP basic auth usernames) but
 * we store it encrypted too for consistency.
 */
class CommunicationSettings extends Settings
{
    // ── Twilio ───────────────────────────────────────────────────────────

    /** Twilio Account SID (starts with "AC"). */
    public ?string $twilio_account_sid = null;

    /** Twilio Auth Token. Encrypted at rest. */
    public ?string $twilio_auth_token = null;

    /** Twilio phone number to send from, in E.164 format: +15551234567. */
    public ?string $twilio_from_number = null;

    // ── SMS ──────────────────────────────────────────────────────────────

    public bool $sms_enabled = false;

    /** Optional double opt-in confirmation message sent after signup. */
    public ?string $sms_opt_in_message = null;

    // ── Voice ─────────────────────────────────────────────────────────────

    public bool $voice_enabled = false;

    // ── Video (Telehealth) ────────────────────────────────────────────────

    /**
     * When enabled, the patient portal displays a video consult join link.
     * Requires PRX to expose the patient video token endpoint.
     */
    public bool $video_enabled = false;

    public static function group(): string
    {
        return 'communication';
    }

    /**
     * @return array<int, string>
     */
    public static function encrypted(): array
    {
        return [
            'twilio_account_sid',
            'twilio_auth_token',
        ];
    }
}
