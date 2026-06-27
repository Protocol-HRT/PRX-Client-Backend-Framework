<?php

namespace App\Actions\EmailSubscribers;

use App\Actions\Concerns\Transacts;
use App\Enums\SubscriberStatus;
use App\Models\EmailSubscriber;

class UnsubscribeAction
{
    use Transacts;

    public function execute(EmailSubscriber $subscriber): EmailSubscriber
    {
        return $this->tx(function () use ($subscriber) {
            $subscriber->update([
                'status' => SubscriberStatus::Unsubscribed,
                'email_consent' => false,
                'unsubscribed_at' => now(),
            ]);

            return $subscriber->fresh();
        });
    }
}
