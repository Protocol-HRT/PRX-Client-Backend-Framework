<?php

namespace App\Models\Payments;

use App\Enums\Payments\GatewayEnvironment;
use App\Enums\Payments\GatewayProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MerchantAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'gateway_provider',
        'environment',
        // NMI credentials
        'nmi_security_key',
        // Authorize.Net credentials
        'authnet_api_login_id',
        'authnet_transaction_key',
        'authnet_public_client_key',
        // Configuration
        'is_active',
        'is_default',
        'transaction_weight',
        'monthly_volume_limit',
        'monthly_volume_used',
        'auto_disable_at_limit',
        'auto_disabled_at',
        'reactivate_on',
        'allows_recurring_payments',
        'notification_thresholds',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'gateway_provider' => GatewayProvider::class,
            'environment' => GatewayEnvironment::class,
            // Sensitive credentials encrypted at rest in addition to DB-level encryption
            'nmi_security_key' => 'encrypted',
            'authnet_api_login_id' => 'encrypted',
            'authnet_transaction_key' => 'encrypted',
            // Public client key is safe to expose in frontend JS — not encrypted
            'authnet_public_client_key' => 'string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'transaction_weight' => 'integer',
            'monthly_volume_limit' => 'decimal:2',
            'monthly_volume_used' => 'decimal:2',
            'auto_disable_at_limit' => 'boolean',
            'auto_disabled_at' => 'datetime',
            'reactivate_on' => 'date',
            'allows_recurring_payments' => 'boolean',
            'notification_thresholds' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MerchantAccount $account): void {
            if (blank($account->uuid)) {
                $account->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Returns the client-safe public key used for JS tokenization.
     * Only Authorize.Net has a distinct public key; NMI tokenization is handled server-side.
     */
    public function getPublicKey(): ?string
    {
        return match ($this->gateway_provider) {
            GatewayProvider::AuthorizeNet => $this->authnet_public_client_key,
            default => null,
        };
    }

    /**
     * Returns true when all credentials required to process transactions are set.
     */
    public function hasValidCredentials(): bool
    {
        return match ($this->gateway_provider) {
            GatewayProvider::Nmi => ! empty($this->nmi_security_key),
            GatewayProvider::AuthorizeNet => ! empty($this->authnet_api_login_id)
                && ! empty($this->authnet_transaction_key),
        };
    }
}
