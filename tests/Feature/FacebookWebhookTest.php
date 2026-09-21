<?php

namespace Tests\Feature;

use App\Models\ConnectedAccount;
use App\Models\Contact;
use App\Models\Location;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Services\Facebook\FacebookLeadData;
use App\Services\Facebook\FacebookLeadsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeFacebookLeadsClient;
use Tests\TestCase;

class FacebookWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const APP_SECRET = 'test-facebook-app-secret';

    private const VERIFY_TOKEN = 'test-webhook-verify-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.facebook_ads.app_secret' => self::APP_SECRET,
            'services.facebook_ads.webhook_verify_token' => self::VERIFY_TOKEN,
        ]);
    }

    private function bindFakeLeadsClient(): FakeFacebookLeadsClient
    {
        $fake = new FakeFacebookLeadsClient();
        $this->app->instance(FacebookLeadsClient::class, $fake);

        return $fake;
    }

    /**
     * @return array{0: Location, 1: Pipeline, 2: ConnectedAccount}
     */
    private function makeLocationWithConnectedFacebookPage(string $pageId): array
    {
        $location = Location::factory()->create();
        $pipeline = Pipeline::factory()->create(['location_id' => $location->id, 'is_default' => true]);
        $pipeline->stages()->create(['name' => 'New Leads', 'position' => 0]);

        $connectedAccount = ConnectedAccount::factory()->create([
            'location_id' => $location->id,
            'provider' => 'facebook',
            'external_account_id' => $pageId,
            'access_token' => 'page-access-token-for-'.$pageId,
        ]);

        return [$location, $pipeline, $connectedAccount];
    }

    private function leadgenPayload(string $pageId, string $leadgenId): array
    {
        return [
            'object' => 'page',
            'entry' => [
                [
                    'id' => $pageId,
                    'time' => 1700000000,
                    'changes' => [
                        [
                            'field' => 'leadgen',
                            'value' => [
                                'leadgen_id' => $leadgenId,
                                'page_id' => $pageId,
                                'form_id' => 'form-123',
                                'ad_id' => 'ad-456',
                                'created_time' => 1700000000,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function signaturesFor(array $payload): string
    {
        return 'sha256='.hash_hmac('sha256', json_encode($payload), self::APP_SECRET);
    }

    public function test_get_verification_with_the_correct_token_echoes_back_the_challenge(): void
    {
        // Literal dots, matching Facebook's actual documented query
        // param names — PHP renames them to hub_mode/hub_verify_token/
        // hub_challenge while parsing the query string, which is exactly
        // the behavior the controller relies on (and documents).
        $response = $this->get(
            '/webhooks/facebook/leads?hub.mode=subscribe&hub.verify_token='.self::VERIFY_TOKEN.'&hub.challenge=challenge-abc-123'
        );

        $response->assertOk();
        $this->assertSame('challenge-abc-123', $response->getContent());
    }

    public function test_get_verification_with_the_wrong_token_is_rejected(): void
    {
        $response = $this->get(
            '/webhooks/facebook/leads?hub.mode=subscribe&hub.verify_token=totally-wrong&hub.challenge=challenge-abc-123'
        );

        $response->assertForbidden();
    }

    public function test_post_with_valid_signature_and_a_matching_page_creates_a_contact_and_opportunity(): void
    {
        [$location, , $connectedAccount] = $this->makeLocationWithConnectedFacebookPage('page-111');
        $fake = $this->bindFakeLeadsClient();
        $fake->willReturn('leadgen-1', new FacebookLeadData(
            leadgenId: 'leadgen-1',
            name: 'Jane Doe',
            email: 'jane@example.com',
            phone: '+15551234567',
            pageId: 'page-111',
        ));

        $payload = $this->leadgenPayload('page-111', 'leadgen-1');

        $response = $this->withHeaders(['X-Hub-Signature-256' => $this->signaturesFor($payload)])
            ->postJson('/webhooks/facebook/leads', $payload);

        $response->assertOk();

        $contact = Contact::withoutGlobalScopes()->sole();
        $this->assertSame($location->id, $contact->location_id);
        $this->assertSame('Jane Doe', $contact->first_name);
        $this->assertSame('jane@example.com', $contact->email);
        $this->assertSame('+15551234567', $contact->phone);

        $opportunity = Opportunity::withoutGlobalScopes()->sole();
        $this->assertSame($location->id, $opportunity->location_id);
        $this->assertSame($contact->id, $opportunity->contact_id);
        $this->assertSame('open', $opportunity->status);
        $this->assertSame('Facebook Lead Ad', $opportunity->source);

        // Fetched using the connected account's own stored token, not
        // some other value.
        $this->assertSame(
            [['leadgen_id' => 'leadgen-1', 'access_token' => $connectedAccount->access_token]],
            $fake->calls
        );
    }

    // The critical one, same rigor as the Twilio equivalent: an invalid
    // or missing signature must be rejected before any other logic
    // runs — no Contact or Opportunity created, and the (fake) Graph
    // API is never even called.
    public function test_post_with_an_invalid_signature_is_rejected_and_creates_nothing(): void
    {
        $this->makeLocationWithConnectedFacebookPage('page-111');
        $fake = $this->bindFakeLeadsClient();

        $payload = $this->leadgenPayload('page-111', 'leadgen-1');

        $response = $this->withHeaders(['X-Hub-Signature-256' => 'sha256=not-the-real-signature'])
            ->postJson('/webhooks/facebook/leads', $payload);

        $response->assertForbidden();
        $this->assertSame(0, Contact::withoutGlobalScopes()->count());
        $this->assertSame(0, Opportunity::withoutGlobalScopes()->count());
        $this->assertSame([], $fake->calls);
    }

    public function test_post_with_a_missing_signature_is_rejected_and_creates_nothing(): void
    {
        $this->makeLocationWithConnectedFacebookPage('page-111');
        $this->bindFakeLeadsClient();

        $payload = $this->leadgenPayload('page-111', 'leadgen-1');

        $response = $this->postJson('/webhooks/facebook/leads', $payload);

        $response->assertForbidden();
        $this->assertSame(0, Contact::withoutGlobalScopes()->count());
    }

    public function test_post_with_a_valid_signature_but_an_unrecognized_page_id_does_nothing_and_returns_200(): void
    {
        // A connected page exists, but for a different page id.
        $this->makeLocationWithConnectedFacebookPage('page-111');
        $fake = $this->bindFakeLeadsClient();

        $payload = $this->leadgenPayload('page-999-unrecognized', 'leadgen-1');

        $response = $this->withHeaders(['X-Hub-Signature-256' => $this->signaturesFor($payload)])
            ->postJson('/webhooks/facebook/leads', $payload);

        $response->assertOk();
        $this->assertSame(0, Contact::withoutGlobalScopes()->count());
        $this->assertSame([], $fake->calls);
    }

    // Two locations, different connected pages: a lead notification for
    // location A's page must never create data under location B, even
    // with contrived overlapping data (same lead name/email used for
    // both).
    public function test_a_lead_for_location_as_page_never_creates_data_under_location_b(): void
    {
        [$locationA] = $this->makeLocationWithConnectedFacebookPage('page-a');
        [$locationB] = $this->makeLocationWithConnectedFacebookPage('page-b');

        $fake = $this->bindFakeLeadsClient();
        $fake->willReturn('leadgen-shared', new FacebookLeadData(
            leadgenId: 'leadgen-shared',
            name: 'Overlap Person',
            email: 'overlap@example.com',
            phone: '+15559998888',
            pageId: 'page-a',
        ));

        $payload = $this->leadgenPayload('page-a', 'leadgen-shared');

        $response = $this->withHeaders(['X-Hub-Signature-256' => $this->signaturesFor($payload)])
            ->postJson('/webhooks/facebook/leads', $payload);

        $response->assertOk();

        $contact = Contact::withoutGlobalScopes()->sole();
        $this->assertSame($locationA->id, $contact->location_id);

        $opportunity = Opportunity::withoutGlobalScopes()->sole();
        $this->assertSame($locationA->id, $opportunity->location_id);

        $this->assertSame(0, Contact::withoutGlobalScopes()->where('location_id', $locationB->id)->count());
        $this->assertSame(0, Opportunity::withoutGlobalScopes()->where('location_id', $locationB->id)->count());
    }

    // If the lead fetch comes back missing name/email/phone (a form
    // didn't collect one, or field-name matching didn't recognize a
    // custom field), the Contact is still created with whatever's
    // available rather than erroring.
    public function test_a_lead_missing_some_fields_still_creates_a_contact_with_whats_available(): void
    {
        [$location] = $this->makeLocationWithConnectedFacebookPage('page-111');
        $fake = $this->bindFakeLeadsClient();
        $fake->willReturn('leadgen-partial', new FacebookLeadData(
            leadgenId: 'leadgen-partial',
            name: null,
            email: 'onlyemail@example.com',
            phone: null,
            pageId: 'page-111',
        ));

        $payload = $this->leadgenPayload('page-111', 'leadgen-partial');

        $response = $this->withHeaders(['X-Hub-Signature-256' => $this->signaturesFor($payload)])
            ->postJson('/webhooks/facebook/leads', $payload);

        $response->assertOk();

        $contact = Contact::withoutGlobalScopes()->sole();
        $this->assertSame($location->id, $contact->location_id);
        // No name came back — falls back to the email rather than
        // leaving the NOT NULL first_name column with nothing at all.
        $this->assertSame('onlyemail@example.com', $contact->first_name);
        $this->assertSame('onlyemail@example.com', $contact->email);
        $this->assertNull($contact->phone);

        $this->assertSame(1, Opportunity::withoutGlobalScopes()->count());
    }
}
