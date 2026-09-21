<?php

namespace Tests\Feature;

use App\Models\ConnectedAccount;
use App\Models\Location;
use App\Models\User;
use App\Services\OAuth\FacebookOAuthClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fakes\FakeFacebookOAuthClient;
use Tests\TestCase;

/**
 * Phase 9 Stage 1: OAuth connection infrastructure. Only FacebookOAuthClient
 * is exercised here (via FakeFacebookOAuthClient, bound in place of
 * FacebookOAuthClientImpl so no test can ever reach a real Facebook
 * endpoint) — GoogleOAuthClient shares the exact same controller code
 * path (provider is just a string), so it isn't duplicated per test.
 *
 * IMPORTANT, not provable by any test here: the actual "redirect to
 * Facebook/Google's real consent screen and complete a real exchange"
 * step cannot be exercised until real developer app credentials exist
 * for this project (FacebookOAuthClientImpl/GoogleOAuthClientImpl are
 * UNVERIFIED — see their own docblocks). What's proven here is
 * everything around that: state generation/verification, the upsert
 * and its tenant scoping, tenant-safe disconnect, and that the stored
 * token is genuinely encrypted at rest.
 */
class ConnectedAccountControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Location}
     */
    private function makeUserWithLocation(): array
    {
        $location = Location::factory()->create();
        $user = User::factory()->create(['current_location_id' => $location->id]);
        $user->locations()->attach($location->id, ['role' => 'owner']);

        return [$user, $location];
    }

    private function bindFakeFacebookClient(): FakeFacebookOAuthClient
    {
        $fake = new FakeFacebookOAuthClient();
        $this->app->instance(FacebookOAuthClient::class, $fake);

        return $fake;
    }

    public function test_connect_redirects_to_the_fakes_authorization_url_and_stores_a_state_value_in_session(): void
    {
        [$user] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $fake = $this->bindFakeFacebookClient();

        $response = $this->get('/connected-accounts/facebook/connect');

        $response->assertStatus(302);
        $target = $response->headers->get('Location');

        $this->assertStringStartsWith($fake->authorizationUrl, $target);
        $this->assertNotEmpty(session('oauth_state.facebook'));

        // The state actually appended to the redirect URL must be the
        // same one stored in session — otherwise callback() would have
        // nothing correct to verify against.
        parse_str((string) parse_url($target, PHP_URL_QUERY), $query);
        $this->assertSame(session('oauth_state.facebook'), $query['state']);

        $this->assertSame([route('connected-accounts.callback', 'facebook')], $fake->authorizationUrlRedirectUris);
    }

    public function test_callback_with_valid_state_creates_a_connected_account_for_the_current_location(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $fake = $this->bindFakeFacebookClient();

        // Primes the session state the way a real connect() redirect would.
        $this->get('/connected-accounts/facebook/connect');
        $state = session('oauth_state.facebook');

        $response = $this->get('/connected-accounts/facebook/callback?state='.$state.'&code=auth-code-123');

        $response->assertRedirect(route('settings.location.edit'));

        // Explicit location_id: this route IS authenticated (see the
        // routing note in routes/web.php), so BelongsToLocation's
        // auto-fill would technically also supply it — but
        // updateOrCreate()'s search array needs an explicit value in
        // its own right to match the unique (location_id, provider)
        // constraint, auto-fill only applies on the eventual insert.
        $account = ConnectedAccount::sole();
        $this->assertSame($location->id, $account->location_id);
        $this->assertSame('facebook', $account->provider);
        $this->assertSame($fake->externalAccountId, $account->external_account_id);
        $this->assertSame($fake->externalAccountName, $account->external_account_name);
        $this->assertSame($fake->accessToken, $account->access_token);
        $this->assertNotNull($account->connected_at);

        $this->assertSame([['code' => 'auth-code-123', 'redirect_uri' => route('connected-accounts.callback', 'facebook')]], $fake->exchangeCalls);

        // One-time use: the state is consumed, not left around to be replayed.
        $this->assertNull(session('oauth_state.facebook'));
    }

    // The important one, same rigor as the Twilio signature test: a
    // forged or missing state must never be treated as "close enough".
    public function test_callback_with_a_mismatched_state_is_rejected(): void
    {
        [$user] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $fake = $this->bindFakeFacebookClient();

        $this->get('/connected-accounts/facebook/connect');

        $response = $this->get('/connected-accounts/facebook/callback?state=not-the-real-state&code=auth-code-123');

        $response->assertForbidden();
        $this->assertSame(0, ConnectedAccount::count());
        $this->assertSame([], $fake->exchangeCalls);
    }

    public function test_callback_with_no_state_at_all_is_rejected(): void
    {
        [$user] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $fake = $this->bindFakeFacebookClient();

        // No prior connect() call — nothing was ever stored in session.
        $response = $this->get('/connected-accounts/facebook/callback?code=auth-code-123');

        $response->assertForbidden();
        $this->assertSame(0, ConnectedAccount::count());
        $this->assertSame([], $fake->exchangeCalls);
    }

    // Proves the unique (location_id, provider) constraint's upsert
    // behavior: reconnecting replaces the old token/account rather than
    // erroring or creating a second row.
    public function test_reconnecting_updates_the_existing_connected_account_rather_than_creating_a_duplicate(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $fake = $this->bindFakeFacebookClient();

        $this->get('/connected-accounts/facebook/connect');
        $this->get('/connected-accounts/facebook/callback?state='.session('oauth_state.facebook').'&code=first-code');

        $original = ConnectedAccount::sole();

        $fake->externalAccountId = 'a-different-page-id';
        $fake->externalAccountName = 'A Different Page';
        $fake->accessToken = 'a-different-access-token';

        $this->get('/connected-accounts/facebook/connect');
        $this->get('/connected-accounts/facebook/callback?state='.session('oauth_state.facebook').'&code=second-code');

        $this->assertSame(1, ConnectedAccount::count());

        $account = ConnectedAccount::sole();
        $this->assertSame($original->id, $account->id);
        $this->assertSame($location->id, $account->location_id);
        $this->assertSame('a-different-page-id', $account->external_account_id);
        $this->assertSame('A Different Page', $account->external_account_name);
        $this->assertSame('a-different-access-token', $account->access_token);
    }

    public function test_cannot_disconnect_another_locations_connected_account(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB, $locationB] = $this->makeUserWithLocation();

        $this->actingAs($userB);
        $accountB = ConnectedAccount::factory()->create(['location_id' => $locationB->id]);

        $this->actingAs($userA);
        $response = $this->delete("/connected-accounts/{$accountB->id}");

        $response->assertNotFound();
        $this->assertNotNull(ConnectedAccount::withoutGlobalScopes()->find($accountB->id));
    }

    public function test_disconnect_deletes_the_authenticated_users_own_connected_account(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $account = ConnectedAccount::factory()->create(['location_id' => $location->id]);

        $response = $this->delete("/connected-accounts/{$account->id}");

        $response->assertRedirect(route('settings.location.edit'));
        $this->assertNull(ConnectedAccount::withoutGlobalScopes()->find($account->id));
    }

    public function test_settings_page_shows_connect_and_disconnect_correctly_per_provider(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        ConnectedAccount::factory()->create([
            'location_id' => $location->id,
            'provider' => 'facebook',
            'external_account_name' => 'My Facebook Page',
        ]);

        $response = $this->get('/settings');

        $response->assertOk();
        // Facebook is connected — shows its name and a Disconnect action.
        $response->assertSee('My Facebook Page');
        $response->assertSee('Disconnect');
        // Google is not — shows a Connect link instead.
        $response->assertSee(route('connected-accounts.connect', 'google'), false);
    }

    // The critical proof for the 'encrypted' cast: the raw column value
    // must never equal the plaintext token, queried directly against the
    // database — bypassing the model (and its cast) entirely.
    public function test_access_token_is_genuinely_encrypted_at_rest(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $plaintext = 'super-secret-plaintext-access-token';

        $account = ConnectedAccount::factory()->create([
            'location_id' => $location->id,
            'access_token' => $plaintext,
        ]);

        $rawValue = DB::table('connected_accounts')->where('id', $account->id)->value('access_token');

        $this->assertNotSame($plaintext, $rawValue);
        $this->assertStringNotContainsString($plaintext, (string) $rawValue);

        // The model still transparently decrypts it back to the original.
        $this->assertSame($plaintext, $account->fresh()->access_token);
    }
}
