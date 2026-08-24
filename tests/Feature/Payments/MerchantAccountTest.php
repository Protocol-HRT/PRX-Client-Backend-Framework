<?php

namespace Tests\Feature\Payments;

use App\Enums\Payments\GatewayEnvironment;
use App\Enums\Payments\GatewayProvider;
use App\Models\Payments\MerchantAccount;
use App\Services\Payments\Gateways\AuthorizeNetGateway;
use App\Services\Payments\Gateways\NmiGateway;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_account_can_be_created(): void
    {
        $account = MerchantAccount::factory()->create([
            'name' => 'Test Account',
            'gateway_provider' => GatewayProvider::AuthorizeNet,
            'environment' => GatewayEnvironment::Sandbox,
        ]);

        $this->assertDatabaseHas('merchant_accounts', ['name' => 'Test Account']);
        $this->assertNotEmpty($account->uuid);
        $this->assertInstanceOf(GatewayProvider::class, $account->gateway_provider);
    }

    public function test_nmi_credentials_are_encrypted(): void
    {
        $account = MerchantAccount::factory()->nmi()->create([
            'nmi_security_key' => 'plaintext-key-abc123',
        ]);

        // Raw DB value must not equal the plaintext
        $raw = \DB::selectOne('SELECT nmi_security_key FROM merchant_accounts WHERE id = ?', [$account->id]);
        $this->assertNotEquals('plaintext-key-abc123', $raw->nmi_security_key);

        // Model decrypts transparently
        $fresh = $account->fresh();
        $this->assertEquals('plaintext-key-abc123', $fresh->nmi_security_key);
    }

    public function test_authorizenet_credentials_are_encrypted(): void
    {
        $account = MerchantAccount::factory()->create([
            'authnet_api_login_id' => 'my-login-id',
            'authnet_transaction_key' => 'my-transaction-key',
        ]);

        $raw = \DB::selectOne('SELECT authnet_api_login_id, authnet_transaction_key FROM merchant_accounts WHERE id = ?', [$account->id]);
        $this->assertNotEquals('my-login-id', $raw->authnet_api_login_id);
        $this->assertNotEquals('my-transaction-key', $raw->authnet_transaction_key);

        $fresh = $account->fresh();
        $this->assertEquals('my-login-id', $fresh->authnet_api_login_id);
        $this->assertEquals('my-transaction-key', $fresh->authnet_transaction_key);
    }

    public function test_authnet_public_client_key_is_not_encrypted(): void
    {
        $account = MerchantAccount::factory()->create([
            'authnet_public_client_key' => 'public-key-safe',
        ]);

        $raw = \DB::selectOne('SELECT authnet_public_client_key FROM merchant_accounts WHERE id = ?', [$account->id]);
        $this->assertEquals('public-key-safe', $raw->authnet_public_client_key);
    }

    public function test_has_valid_credentials_nmi(): void
    {
        $withKey = MerchantAccount::factory()->nmi()->create(['nmi_security_key' => 'key']);
        $this->assertTrue($withKey->hasValidCredentials());

        $withoutKey = MerchantAccount::factory()->nmi()->create(['nmi_security_key' => null]);
        $this->assertFalse($withoutKey->hasValidCredentials());
    }

    public function test_has_valid_credentials_authorizenet(): void
    {
        $full = MerchantAccount::factory()->create([
            'authnet_api_login_id' => 'login',
            'authnet_transaction_key' => 'txkey',
        ]);
        $this->assertTrue($full->hasValidCredentials());

        $partial = MerchantAccount::factory()->create([
            'authnet_api_login_id' => 'login',
            'authnet_transaction_key' => null,
        ]);
        $this->assertFalse($partial->hasValidCredentials());
    }

    public function test_get_public_key_returns_authnet_public_client_key(): void
    {
        $account = MerchantAccount::factory()->create([
            'gateway_provider' => GatewayProvider::AuthorizeNet,
            'authnet_public_client_key' => 'js-key',
        ]);

        $this->assertEquals('js-key', $account->getPublicKey());
    }

    public function test_get_public_key_returns_null_for_nmi(): void
    {
        $account = MerchantAccount::factory()->nmi()->create();

        $this->assertNull($account->getPublicKey());
    }

    public function test_payment_gateway_manager_resolves_authorizenet_gateway(): void
    {
        $account = MerchantAccount::factory()->create(['gateway_provider' => GatewayProvider::AuthorizeNet]);
        $manager = app(PaymentGatewayManager::class);

        $gateway = $manager->forAccount($account);

        $this->assertInstanceOf(AuthorizeNetGateway::class, $gateway);
    }

    public function test_payment_gateway_manager_resolves_nmi_gateway(): void
    {
        $account = MerchantAccount::factory()->nmi()->create();
        $manager = app(PaymentGatewayManager::class);

        $gateway = $manager->forAccount($account);

        $this->assertInstanceOf(NmiGateway::class, $gateway);
    }

    public function test_payment_gateway_manager_resolves_default_account(): void
    {
        MerchantAccount::factory()->create(['is_default' => false, 'is_active' => true]);
        $default = MerchantAccount::factory()->create(['is_default' => true, 'is_active' => true]);

        $manager = app(PaymentGatewayManager::class);
        $gateway = $manager->default();

        $this->assertNotNull($gateway);
    }

    public function test_soft_delete_removes_from_queries(): void
    {
        $account = MerchantAccount::factory()->create();
        $account->delete();

        $this->assertSoftDeleted('merchant_accounts', ['id' => $account->id]);
        $this->assertNull(MerchantAccount::find($account->id));
    }

    public function test_uuid_is_auto_generated(): void
    {
        $account = MerchantAccount::factory()->create(['uuid' => null]);

        $this->assertNotEmpty($account->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $account->uuid,
        );
    }

    public function test_factory_uuid_is_not_overridden_when_set(): void
    {
        $uuid = '11111111-1111-1111-1111-111111111111';
        $account = MerchantAccount::factory()->create(['uuid' => $uuid]);

        $this->assertEquals($uuid, $account->uuid);
    }
}
