<?php

namespace App\Integrations\Drivers;

use App\Integrations\Contracts\SendsTransactionalEmail;
use App\Integrations\Messages\EmailMessage;
use App\Models\Integrations\IntegrationInstance;
use App\Services\Mail\MailConfigurator;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * This installation's own mail stack, offered as an integration.
 *
 * ─── Why the site's own mailer is an "integration" at all ──────────────
 *
 * Because otherwise `send_email` has two implementations: one special case for
 * "just use our mail settings" and one for every configured vendor. Two paths
 * means two places for a consent check, a PHI check and a failure record to
 * live, and the special case is always the one that skips them.
 *
 * So the local mailer registers like any other provider, and `send_email`
 * becomes uniformly capability-routed: an install that has configured nothing
 * else still has exactly one instance offering `transactional_email`, and the
 * action works on day one with no vendor account anywhere.
 *
 * It deliberately does NOT offer marketing email. The site mailer sends one
 * message to one person about something they just did; a campaign needs
 * suppression lists, unsubscribe handling and consent tracking that Laravel's
 * mailer has no idea about. Claiming that capability would let an operator build
 * a nurture sequence on top of a transport that cannot honour an opt-out.
 *
 * ─── It holds no credentials ───────────────────────────────────────────
 *
 * The keys live in `CommunicationSettings`, where the operator already manages
 * them and where `MailConfigurator` already reads them. Copying them onto an
 * instance row would create a second copy that can disagree with the first, and
 * the mail settings page would silently stop being the truth.
 */
class LocalMailDriver implements SendsTransactionalEmail
{
    public function __construct(private readonly MailConfigurator $mail) {}

    /**
     * Confirm this install could actually deliver a message.
     *
     * `canSend()` is stricter than "is a mailer configured", and deliberately:
     * an install pointed at the `log` or `array` transport has a mailer that
     * works perfectly and reaches nobody. An operator testing this connection
     * wants to know that, not to be congratulated.
     */
    public function test(IntegrationInstance $instance): void
    {
        $this->mail->apply();

        if (! $this->mail->canSend()) {
            throw new RuntimeException(
                'This installation cannot currently send email. Check Settings → Communications: '
                .'email must be switched on, and the provider must be a real transport rather than '
                .'"log" or "array", which accept everything and deliver nothing.'
            );
        }
    }

    public function sendEmail(IntegrationInstance $instance, EmailMessage $message): array
    {
        $this->test($instance);

        Mail::raw($message->body, function ($mail) use ($message): void {
            $mail->to($message->to, $message->toName)->subject($message->subject);
        });

        // Deliberately thin. Whatever a handler returns is written to
        // `workflow_action_runs.output`, which is an unencrypted table any admin
        // with run-log access can read — so it records THAT a message went to an
        // address, never what the message said.
        return [
            'delivered_to' => $message->to,
            'transport' => config('mail.default'),
        ];
    }
}
