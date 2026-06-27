<?php

namespace App\Enums;

enum SubscriberStatus: string
{
    case Subscribed = 'subscribed';
    case Unsubscribed = 'unsubscribed';
    case Bounced = 'bounced';

    public function label(): string
    {
        return match ($this) {
            self::Subscribed => 'Subscribed',
            self::Unsubscribed => 'Unsubscribed',
            self::Bounced => 'Bounced',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Subscribed => 'success',
            self::Unsubscribed => 'gray',
            self::Bounced => 'danger',
        };
    }
}
