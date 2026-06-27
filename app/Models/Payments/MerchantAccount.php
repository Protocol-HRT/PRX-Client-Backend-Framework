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
        'nmi_public_key',
        // Authorize.Net credentials
        'authnet_api_login_id',
        'authnet_transaction_key',
        'authnet_public_client_key',
        'authnet_signature_key',
        'cim_enabled',
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
        'allows_rx_processing',
        'allows_card_present',
        'allows_card_not_present',
        'supports_public_checkout',
        'notification_thresholds',
        // Surcharge — inherited by all orders routed through this account
        'surcharge_rate',
        'surcharge_flat_per_txn',
        'surcharge_passthrough',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'gateway_provider' => GatewayProvider::class,
            'environment' => GatewayEnvironment::class,
            // Server-side secrets — encrypted at rest
            'nmi_security_key' => 'encrypted',
            'authnet_api_login_id' => 'encrypted',
            'authnet_transaction_key' => 'encrypted',
            'authnet_signature_key' => 'encrypted',
            // Client-safe keys — plain text, safe to expose to browser JS
            'nmi_public_key' => 'string',
            'authnet_public_client_key' => 'string',
            'cim_enabled' => 'boolean',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'transaction_weight' => 'integer',
            'monthly_volume_limit' => 'decimal:2',
            'monthly_volume_used' => 'decimal:2',
            'auto_disable_at_limit' => 'boolean',
            'auto_disabled_at' => 'datetime',
            'reactivate_on' => 'date',
            'allows_recurring_payments' => 'boolean',
            'allows_rx_processing' => 'boolean',
            'allows_card_present' => 'boolean',
            'allows_card_not_present' => 'boolean',
            'supports_public_checkout' => 'boolean',
            'notification_thresholds' => 'array',
            'surcharge_rate' => 'decimal:4',
            'surcharge_flat_per_txn' => 'decimal:2',
            'surcharge_passthrough' => 'boolean',
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
     * Client-safe public key for JS tokenization (Collect.js / Accept.js).
     * Never returns encrypted server-side credentials.
     */
    public function getPublicKey(): ?string
    {
        return match ($this->gateway_provider) {
            GatewayProvider::Nmi => $this->nmi_public_key,
            GatewayProvider::AuthorizeNet => $this->authnet_public_client_key,
        };
    }

    /**
     * Returns true when all credentials required to process a transaction are present.
     * Webhook-only fields (authnet_signature_key) are not required here.
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
