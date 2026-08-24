<?php

namespace App\Models;

use App\Enums\SubscriberStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EmailSubscriber extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'email',
        'first_name',
        'last_name',
        'phone',
        'source',
        'status',
        'email_consent',
        'sms_consent',
        'consent_given_at',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'referrer',
        'landing_url',
        'ip_address',
        'user_agent',
        'subscribed_at',
        'unsubscribed_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriberStatus::class,
            'email_consent' => 'boolean',
            'sms_consent' => 'boolean',
            'consent_given_at' => 'datetime',
            'subscribed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EmailSubscriber $sub): void {
            if (blank($sub->uuid)) {
                $sub->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
