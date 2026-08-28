<?php

namespace App\Models;

use App\Enums\CheckoutPath;
use App\Enums\LeadStatus;
use App\Models\Commerce\Encounter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Lead extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'cart_ulid',
        'status',
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'age',
        'gender',
        'quiz_answers',
        'quiz_id',
        'quiz_completed_at',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'sms_consent',
        'email_consent',
        'consent_given_at',
        'cart_items',
        'cart_subtotal',
        'checkout_path',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'referrer',
        'landing_url',
        'user_agent',
        'ip_address',
        'prescribe_rx_encounter_id',
        'prescribe_rx_patient_id',
        'handed_off_at',
        'completed_at',
        'prescribe_rx_response',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'checkout_path' => CheckoutPath::class,
            'date_of_birth' => 'date',
            'age' => 'integer',
            'quiz_answers' => 'array',
            'quiz_completed_at' => 'datetime',
            'consent_given_at' => 'datetime',
            'handed_off_at' => 'datetime',
            'completed_at' => 'datetime',
            'sms_consent' => 'boolean',
            'email_consent' => 'boolean',
            'cart_items' => 'array',
            'prescribe_rx_response' => 'array',
            'cart_subtotal' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Lead $lead): void {
            if (blank($lead->uuid)) {
                $lead->uuid = (string) Str::uuid();
            }
            if (blank($lead->status)) {
                $lead->status = LeadStatus::New_;
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

    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class);
    }

    /**
     * The visitor's age, from the best source this lead actually has.
     *
     * `date_of_birth` wins when present because it is what a completed
     * clinical intake captured and it stays correct as time passes; `age` is
     * what the marketing quiz captured and is a snapshot of the day it was
     * answered. Returns null when neither exists — which the recommendation
     * resolver reads as "not asked", not as "no restriction applies".
     */
    public function effectiveAge(): ?int
    {
        return $this->date_of_birth?->age ?? $this->age;
    }
}
