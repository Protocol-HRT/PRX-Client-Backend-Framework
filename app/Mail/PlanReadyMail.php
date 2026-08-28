<?php

namespace App\Mail;

use App\Models\Lead;
use App\Settings\BrandSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Your plan is ready."
 *
 * Deliberately thin, and it stays thin: this is the FIRST mailable in the
 * install, and the template-builder work is what will eventually own the copy.
 * Hardcoding a marketing body here would create a second place email content
 * lives, and the second one always wins by accident.
 *
 * So the subject and body are the minimum that can be true — the visitor's
 * name and a link back to the plan they already saw — and the moment
 * `document_templates` exists this becomes a renderer for whatever the
 * operator authored.
 */
class PlanReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Lead $lead,
        public readonly string $planUrl,
    ) {}

    public function envelope(): Envelope
    {
        $brand = rescue(fn (): ?string => app(BrandSettings::class)->name, null, false);

        return new Envelope(
            subject: trim(($brand ? $brand.': ' : '').'Your plan is ready'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.plan-ready',
            with: [
                'firstName' => $this->lead->first_name,
                'planUrl' => $this->planUrl,
            ],
        );
    }
}
