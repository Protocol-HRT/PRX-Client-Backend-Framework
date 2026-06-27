<?php

namespace App\Actions\EmailSubscribers;

use App\Actions\Concerns\Transacts;
use App\Data\EmailSubscribers\SubscribeData;
use App\Enums\SubscriberStatus;
use App\Models\EmailSubscriber;

/**
 * Idempotent email-subscribe action. Looks up by email; if the row exists we
 * upgrade non-empty fields (so a fuller capture later can fill in the name /
 * phone we didn't have at the first touchpoint), and resurrect the row if it
 * was previously unsubscribed.
 *
 * UTM/IP/user_agent are written only on first creation so we keep the *first
 * touch* attribution and don't overwrite it on later resubscribes.
 */
class SubscribeEmailAction
{
    use Transacts;

    public function execute(SubscribeData $data): EmailSubscriber
    {
        return $this->tx(function () use ($data) {
            $email = strtolower(trim($data->email));

            $existing = EmailSubscriber::query()->where('email', $email)->first();

            if ($existing) {
                $existing->fill([
                    'first_name' => $data->first_name ?: $existing->first_name,
                    'last_name' => $data->last_name ?: $existing->last_name,
                    'phone' => $data->phone ?: $existing->phone,
                    'source' => $existing->source ?: $data->source,
                    'status' => SubscriberStatus::Subscribed,
                    'email_consent' => $data->email_consent || $existing->email_consent,
                    'sms_consent' => $data->sms_consent || $existing->sms_consent,
                    'consent_given_at' => $existing->consent_given_at ?? now(),
                    'subscribed_at' => $existing->subscribed_at ?? now(),
                    'unsubscribed_at' => null,
                ]);

                if ($data->meta) {
                    $existing->meta = array_replace($existing->meta ?? [], $data->meta);
                }

                $existing->save();

                return $existing->fresh();
            }

            return EmailSubscriber::create([
                'email' => $email,
                'first_name' => $data->first_name,
                'last_name' => $data->last_name,
                'phone' => $data->phone,
                'source' => $data->source,
                'status' => SubscriberStatus::Subscribed,
                'email_consent' => $data->email_consent,
                'sms_consent' => $data->sms_consent,
                'consent_given_at' => now(),
                'utm_source' => $data->utm_source,
                'utm_medium' => $data->utm_medium,
                'utm_campaign' => $data->utm_campaign,
                'utm_term' => $data->utm_term,
                'utm_content' => $data->utm_content,
                'referrer' => $data->referrer,
                'landing_url' => $data->landing_url,
                'ip_address' => $data->ip_address,
                'user_agent' => $data->user_agent,
                'subscribed_at' => now(),
                'meta' => $data->meta,
            ]);
        });
    }
}
