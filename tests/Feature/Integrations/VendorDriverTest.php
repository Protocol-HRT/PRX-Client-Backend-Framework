<?php

namespace Tests\Feature\Integrations;

use App\Enums\Integrations\IntegrationCapability;
use App\Integrations\Contracts\EnrollsInAutomations;
use App\Integrations\Contracts\TracksEvents;
use App\Integrations\Drivers\GoHighLevelDriver;
use App\Integrations\Drivers\KlaviyoDriver;
use App\Integrations\Drivers\TwilioDriver;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Messages\ConsentState;
use App\Integrations\Messages\ContactPayload;
use App\Integrations\Messages\SmsMessage;
use App\Models\Integrations\IntegrationInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * The shipped vendor drivers.
 *
 * ─── WHAT THESE TESTS DO AND DO NOT PROVE ──────────────────────────────
 *
 * They pin the REQUEST SHAPE — url, auth header, API-version header, body — and
 * the handling of what comes back. They cannot prove any vendor accepts that
 * shape, because none of these were built against a live account. Read a green
 * run here as "this driver does what we intended", never as "this driver works".
 * The request shapes are the part to check first with real credentials.
 *
 * What they genuinely do catch: a header dropped in a refactor, an error body
 * swallowed instead of surfaced, a capability claimed that the class cannot
 * honour, and — the one most likely to bite — a message body leaking into the
 * run log, which is an unencrypted table.
 */
class VendorDriverTest extends TestCase
{
    use RefreshDatabase;

    // ─── Capability shape ────────────────────────────────────────────

    public function test_the_two_crm_vendors_declare_opposite_capabilities(): void
    {
        // The reason narrow interfaces exist. Klaviyo has events and forbids
        // direct enrolment; GoHighLevel has no events API and allows enrolment.
        // If either ever claims both, someone has implemented a method by
        // throwing from it.
        $this->assertTrue(is_a(KlaviyoDriver::class, TracksEvents::class, true));
        $this->assertFalse(is_a(KlaviyoDriver::class, EnrollsInAutomations::class, true));

        $this->assertTrue(is_a(GoHighLevelDriver::class, EnrollsInAutomations::class, true));
        $this->assertFalse(is_a(GoHighLevelDriver::class, TracksEvents::class, true));
    }

    public function test_every_shipped_provider_is_registered_and_offers_something(): void
    {
        $registry = app(IntegrationRegistry::class);

        $this->assertSame(
            ['local_mail', 'klaviyo', 'gohighlevel', 'twilio'],
            array_keys($registry->providerOptions()),
        );

        $this->assertSame([IntegrationCapability::Crm], $registry->capabilitiesFor('klaviyo'));
        $this->assertSame([IntegrationCapability::Crm], $registry->capabilitiesFor('gohighlevel'));
        $this->assertSame([IntegrationCapability::Sms], $registry->capabilitiesFor('twilio'));
    }

    // ─── Klaviyo ─────────────────────────────────────────────────────

    public function test_klaviyo_sends_its_auth_and_revision_headers(): void
    {
        // The revision header is not optional and not cosmetic — Klaviyo pins
        // behaviour to it, so a dropped header silently changes response shapes
        // rather than failing outright.
        Http::fake(['a.klaviyo.com/*' => Http::response(['data' => ['id' => '01ABC']], 200)]);

        app(KlaviyoDriver::class)->upsertContact(
            $this->klaviyo(),
            new ContactPayload(email: 'someone@example.invalid'),
        );

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://a.klaviyo.com/api/profile-import/'
                && $request->hasHeader('Authorization', 'Klaviyo-API-Key pk_test')
                && $request->hasHeader('revision', '2026-07-15')
                && $request['data']['type'] === 'profile'
                && $request['data']['attributes']['email'] === 'someone@example.invalid';
        });
    }

    public function test_klaviyo_needs_something_to_identify_a_person_by(): void
    {
        // Without email or phone, Klaviyo would mint a fresh unreachable profile
        // on every single run.
        Http::fake();

        $this->expectException(RuntimeException::class);
        app(KlaviyoDriver::class)->upsertContact($this->klaviyo(), new ContactPayload(firstName: 'Nameless'));
    }

    public function test_klaviyo_resolves_a_group_name_to_the_mapped_list_id(): void
    {
        // Workflow steps name a list; Klaviyo needs an opaque id. The map lives
        // on the instance so rebuilding a list does not break every step.
        Http::fake(['a.klaviyo.com/*' => Http::response([], 204)]);

        app(KlaviyoDriver::class)->addToGroup($this->klaviyo(), '01ABC', 'quiz-completers', new ContactPayload);

        Http::assertSent(fn ($request): bool => $request->url()
            === 'https://a.klaviyo.com/api/lists/XyZ123/relationships/profiles/');
    }

    public function test_klaviyo_refuses_an_unmapped_group_rather_than_guessing(): void
    {
        // Sending the alias through would 404 with nothing explaining why.
        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/No Klaviyo list is mapped/');

        app(KlaviyoDriver::class)->addToGroup($this->klaviyo(), '01ABC', 'never-mapped-name', new ContactPayload);
    }

    public function test_klaviyo_surfaces_the_vendors_own_error_message(): void
    {
        // "Request failed" sends an operator to a log they cannot read.
        Http::fake(['a.klaviyo.com/*' => Http::response([
            'errors' => [['detail' => 'The API key provided is invalid.']],
        ], 401)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/The API key provided is invalid/');

        app(KlaviyoDriver::class)->test($this->klaviyo());
    }

    public function test_klaviyo_event_properties_do_not_reach_the_run_log(): void
    {
        // The returned array is written to workflow_action_runs.output, which is
        // unencrypted and readable by any admin with run-log access.
        Http::fake(['a.klaviyo.com/*' => Http::response([], 202)]);

        $result = app(KlaviyoDriver::class)->trackEvent(
            $this->klaviyo(),
            new ContactPayload(email: 'someone@example.invalid'),
            'Completed Quiz',
            ['blood_pressure' => 'yes'],
        );

        $this->assertStringNotContainsString('blood_pressure', json_encode($result));
        $this->assertSame('Completed Quiz', $result['metric']);
    }

    public function test_klaviyo_does_not_duplicate_identity_fields_as_custom_properties(): void
    {
        // `withIdentity()` always puts email/phone/name into the mapped set, and
        // they are already first-class attributes on a Klaviyo profile. Passing
        // them through as custom properties too gives every profile four
        // duplicates that can drift from the real ones.
        Http::fake(['a.klaviyo.com/*' => Http::response(['data' => ['id' => '01ABC']], 200)]);

        app(KlaviyoDriver::class)->upsertContact($this->klaviyo(), new ContactPayload(
            email: 'someone@example.invalid',
            firstName: 'Sam',
            attributes: ['email' => 'someone@example.invalid', 'first_name' => 'Sam', 'goal' => 'sleep'],
        ));

        Http::assertSent(function ($request): bool {
            $attributes = $request['data']['attributes'];

            return $attributes['email'] === 'someone@example.invalid'
                && $attributes['properties'] === ['goal' => 'sleep'];
        });
    }

    public function test_an_ordinary_list_name_is_not_mistaken_for_a_list_id(): void
    {
        // THE FOOTGUN. A looser rule matches six-letter names like "buyers" and
        // sends them off as bogus ids, skipping the "you forgot to map this"
        // error written for exactly this mistake — so the request still fails,
        // but wearing Klaviyo's 404 instead of the sentence that says what to do.
        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/No Klaviyo list is mapped/');

        app(KlaviyoDriver::class)->addToGroup($this->klaviyo(), '01ABC', 'buyers', new ContactPayload);
    }

    public function test_a_real_looking_list_id_still_passes_through_unmapped(): void
    {
        // The escape hatch has to keep working: an operator who prefers ids
        // should not be made to invent aliases for them.
        Http::fake(['a.klaviyo.com/*' => Http::response([], 204)]);

        app(KlaviyoDriver::class)->addToGroup($this->klaviyo(), '01ABC', 'Ru4Ff9', new ContactPayload);

        Http::assertSent(fn ($request): bool => $request->url()
            === 'https://a.klaviyo.com/api/lists/Ru4Ff9/relationships/profiles/');
    }

    public function test_klaviyo_subscribes_rather_than_merely_adding_when_consent_exists(): void
    {
        // THE DEFECT THIS SLICE EXISTS FOR. The relationships endpoint puts a
        // profile on a list and sets NO consent, so Klaviyo suppresses the send
        // while the "Added to List" flow still fires — a funnel that looks like
        // it works, a run log that says success, and an email nobody receives.
        Http::fake(['a.klaviyo.com/*' => Http::response([], 202)]);

        app(KlaviyoDriver::class)->addToGroup($this->klaviyo(), '01ABC', 'Ru4Ff9', new ContactPayload(
            email: 'someone@example.invalid',
            consent: ConsentState::forChannels(['email']),
        ));

        Http::assertSent(function ($request): bool {
            $profile = $request['data']['attributes']['profiles']['data'][0];

            return $request->url() === 'https://a.klaviyo.com/api/profile-subscription-bulk-create-jobs/'
                && $request['data']['relationships']['list']['data']['id'] === 'Ru4Ff9'
                && $profile['id'] === '01ABC'
                && $profile['attributes']['email'] === 'someone@example.invalid'
                && $profile['attributes']['subscriptions']['email']['marketing']['consent'] === 'SUBSCRIBED';
        });
    }

    public function test_klaviyo_subscribes_only_the_channels_actually_granted(): void
    {
        // `subscriptions` is per channel and an omitted channel is left alone,
        // so an email-only consent must not quietly opt somebody into texts.
        Http::fake(['a.klaviyo.com/*' => Http::response([], 202)]);

        app(KlaviyoDriver::class)->addToGroup($this->klaviyo(), '01ABC', 'Ru4Ff9', new ContactPayload(
            email: 'someone@example.invalid',
            phone: '+15550123',
            consent: ConsentState::forChannels(['email']),
        ));

        Http::assertSent(function ($request): bool {
            $subscriptions = $request['data']['attributes']['profiles']['data'][0]['attributes']['subscriptions'];

            return array_keys($subscriptions) === ['email'];
        });
    }

    public function test_klaviyo_will_not_subscribe_a_channel_it_has_no_identifier_for(): void
    {
        // An SMS grant with no phone number subscribes nothing. Sending it for
        // Klaviyo to reject would turn a consent we hold into a failed run.
        Http::fake(['a.klaviyo.com/*' => Http::response([], 204)]);

        app(KlaviyoDriver::class)->addToGroup($this->klaviyo(), '01ABC', 'Ru4Ff9', new ContactPayload(
            email: 'someone@example.invalid',
            consent: ConsentState::forChannels(['sms']),
        ));

        Http::assertSent(fn ($request): bool => $request->url()
            === 'https://a.klaviyo.com/api/lists/Ru4Ff9/relationships/profiles/');
    }

    public function test_klaviyo_does_not_send_consented_at(): void
    {
        // Klaviyo accepts `consented_at` only under `historical_import: true`,
        // which also bypasses double opt-in and the "Added to List" flows —
        // wrong for a live capture. Our own timestamp evidence is in
        // `lead_consents` and does not need to travel.
        Http::fake(['a.klaviyo.com/*' => Http::response([], 202)]);

        app(KlaviyoDriver::class)->addToGroup($this->klaviyo(), '01ABC', 'Ru4Ff9', new ContactPayload(
            email: 'someone@example.invalid',
            consent: ConsentState::forChannels(['email']),
        ));

        Http::assertSent(fn ($request): bool => ! str_contains(json_encode($request->data()), 'consented_at')
            && ! str_contains(json_encode($request->data()), 'historical_import'));
    }

    // ─── GoHighLevel ─────────────────────────────────────────────────

    public function test_gohighlevel_scopes_every_call_to_its_location(): void
    {
        Http::fake(['services.leadconnectorhq.com/*' => Http::response(['contact' => ['id' => 'ghl-1']], 200)]);

        app(GoHighLevelDriver::class)->upsertContact(
            $this->ghl(),
            new ContactPayload(email: 'someone@example.invalid'),
        );

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://services.leadconnectorhq.com/contacts/upsert'
                && $request->hasHeader('Version', '2021-07-28')
                && $request->hasHeader('Authorization', 'Bearer ghl_token')
                && $request['locationId'] === 'loc_123';
        });
    }

    public function test_gohighlevel_without_a_location_says_so(): void
    {
        Http::fake();

        $instance = $this->ghl();
        $instance->update(['settings' => []]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Location ID/');

        app(GoHighLevelDriver::class)->upsertContact($instance->fresh(), new ContactPayload(email: 'a@example.invalid'));
    }

    public function test_gohighlevel_passes_a_group_through_as_a_tag(): void
    {
        // No lists at this vendor. The same workflow step that becomes a list id
        // at Klaviyo becomes a plain string here — which is the whole reason
        // addToGroup takes a name rather than an id.
        Http::fake(['services.leadconnectorhq.com/*' => Http::response([], 200)]);

        app(GoHighLevelDriver::class)->addToGroup($this->ghl(), 'ghl-1', 'quiz-completers', new ContactPayload);

        Http::assertSent(fn ($request): bool => $request->url()
            === 'https://services.leadconnectorhq.com/contacts/ghl-1/tags'
            && $request['tags'] === ['quiz-completers']);
    }

    public function test_gohighlevel_maps_extra_attributes_to_custom_fields(): void
    {
        // GoHighLevel takes these as a keyed list, not a flat object; sending an
        // object drops them silently.
        Http::fake(['services.leadconnectorhq.com/*' => Http::response(['contact' => ['id' => 'ghl-1']], 200)]);

        app(GoHighLevelDriver::class)->upsertContact($this->ghl(), new ContactPayload(
            email: 'someone@example.invalid',
            attributes: ['email' => 'someone@example.invalid', 'goal' => 'weight loss'],
        ));

        Http::assertSent(function ($request): bool {
            // `email` is a standard field and must not be duplicated as custom.
            return $request['customFields'] === [['key' => 'goal', 'field_value' => 'weight loss']];
        });
    }

    public function test_gohighlevel_enrols_a_contact_at_the_top_of_a_workflow(): void
    {
        Http::fake(['services.leadconnectorhq.com/*' => Http::response([], 200)]);

        app(GoHighLevelDriver::class)->enroll($this->ghl(), 'ghl-1', 'wf-9');

        Http::assertSent(fn ($request): bool => $request->url()
            === 'https://services.leadconnectorhq.com/contacts/ghl-1/workflow/wf-9'
            && filled($request['eventStartTime']));
    }

    // ─── Twilio ──────────────────────────────────────────────────────

    public function test_twilio_posts_form_encoded_with_basic_auth(): void
    {
        // Twilio's REST API is form-encoded. Sending JSON returns a 400 that
        // does not explain itself.
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1', 'status' => 'queued'], 201)]);

        app(TwilioDriver::class)->sendSms($this->twilio(), new SmsMessage(to: '+15550000', body: 'Hello.'));

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC123/Messages.json'
                && $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded')
                && $request['To'] === '+15550000'
                && $request['Body'] === 'Hello.';
        });
    }

    public function test_twilio_prefers_a_messaging_service_over_a_bare_number(): void
    {
        // A messaging service carries the sender pool, opt-out handling and
        // compliance registration. An install that configured one meant it.
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1', 'status' => 'queued'], 201)]);

        $instance = $this->twilio();
        $instance->update(['settings' => ['from_number' => '+15551111', 'messaging_service_sid' => 'MG9']]);

        app(TwilioDriver::class)->sendSms($instance->fresh(), new SmsMessage(to: '+15550000', body: 'Hi.'));

        Http::assertSent(fn ($request): bool => $request['MessagingServiceSid'] === 'MG9'
            && ! isset($request['From']));
    }

    public function test_twilio_with_no_sender_at_all_says_so(): void
    {
        Http::fake();

        $instance = $this->twilio();
        $instance->update(['settings' => []]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no sender/');

        app(TwilioDriver::class)->sendSms($instance->fresh(), new SmsMessage(to: '+15550000', body: 'Hi.'));
    }

    public function test_twilio_does_not_put_the_message_body_in_the_run_log(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1', 'status' => 'queued'], 201)]);

        $result = app(TwilioDriver::class)->sendSms(
            $this->twilio(),
            new SmsMessage(to: '+15550000', body: 'Your results are ready, and they mention a diagnosis.'),
        );

        $this->assertSame(['message_sid' => 'SM1', 'status' => 'queued'], $result);
    }

    public function test_a_missing_credential_is_named_rather_than_crashing(): void
    {
        // Credentials live in a blob an operator half-filled once, so this is a
        // real state and "undefined array key" is a useless answer to it.
        Http::fake();

        $instance = IntegrationInstance::create([
            'name' => 'Half-configured', 'provider' => 'twilio', 'capabilities' => ['sms'],
            'credentials' => ['account_sid' => 'AC123'],
            'settings' => ['from_number' => '+15551111'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/auth token/');

        app(TwilioDriver::class)->sendSms($instance, new SmsMessage(to: '+15550000', body: 'Hi.'));
    }

    // ─── retry behaviour ─────────────────────────────────────────────

    public function test_a_rate_limit_is_retried_and_then_succeeds(): void
    {
        // PROVING THE RETRY IS NOT DEAD CODE. The predicate in
        // TalksToVendorApi::http() only fires because Laravel hands the `when`
        // callback a RequestException built from the failed response — which is
        // not obvious from the signature, and would silently never retry if it
        // were wrong. A rate limit is the one failure worth waiting out.
        Http::fake(['a.klaviyo.com/*' => Http::sequence()
            ->push(['errors' => [['detail' => 'Too many requests']]], 429)
            ->push(['data' => ['id' => '01ABC']], 200)]);

        $id = app(KlaviyoDriver::class)->upsertContact(
            $this->klaviyo(),
            new ContactPayload(email: 'someone@example.invalid'),
        );

        $this->assertSame('01ABC', $id);
        Http::assertSentCount(2);
    }

    public function test_a_bad_request_is_not_retried(): void
    {
        // A 4xx will be wrong again. Retrying it burns rate limit and delays the
        // error the operator needs — and these calls are not idempotent, so a
        // blind retry can enrol somebody twice.
        Http::fake(['a.klaviyo.com/*' => Http::response(['errors' => [['detail' => 'Invalid email']]], 400)]);

        try {
            app(KlaviyoDriver::class)->upsertContact(
                $this->klaviyo(),
                new ContactPayload(email: 'nonsense'),
            );
            $this->fail('a 400 should surface, not be swallowed');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Invalid email', $e->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_a_credential_echoed_back_by_a_vendor_is_redacted(): void
    {
        // NOT HYPOTHETICAL: several APIs quote the offending key in their error
        // ("the key ... is not valid"). That message is written verbatim to
        // workflow_action_runs.error, an unencrypted table any admin with
        // run-log access can read, so it would quietly persist a live secret.
        Http::fake(['a.klaviyo.com/*' => Http::response(
            ['errors' => [['detail' => 'The key pk_live_supersecret is not valid']]], 401
        )]);

        $instance = $this->klaviyo();
        $instance->update(['credentials' => ['private_key' => 'pk_live_supersecret']]);

        try {
            app(KlaviyoDriver::class)->test($instance->fresh());
            $this->fail('expected a failure');
        } catch (RuntimeException $e) {
            // The useful half survives; the secret does not.
            $this->assertStringContainsString('not valid', $e->getMessage());
            $this->assertStringNotContainsString('pk_live_supersecret', $e->getMessage());
            $this->assertStringContainsString('[redacted]', $e->getMessage());
        }
    }

    // ─── fixtures ────────────────────────────────────────────────────

    private function klaviyo(): IntegrationInstance
    {
        return IntegrationInstance::create([
            'name' => 'Klaviyo — Marketing',
            'provider' => 'klaviyo',
            'capabilities' => ['crm'],
            'credentials' => ['private_key' => 'pk_test'],
            'settings' => ['lists' => [['name' => 'quiz-completers', 'list_id' => 'XyZ123']]],
        ]);
    }

    private function ghl(): IntegrationInstance
    {
        return IntegrationInstance::create([
            'name' => 'GoHighLevel',
            'provider' => 'gohighlevel',
            'capabilities' => ['crm'],
            'credentials' => ['access_token' => 'ghl_token'],
            'settings' => ['location_id' => 'loc_123'],
        ]);
    }

    private function twilio(): IntegrationInstance
    {
        return IntegrationInstance::create([
            'name' => 'Twilio',
            'provider' => 'twilio',
            'capabilities' => ['sms'],
            'credentials' => ['account_sid' => 'AC123', 'auth_token' => 'tok'],
            'settings' => ['from_number' => '+15551111'],
        ]);
    }
}
