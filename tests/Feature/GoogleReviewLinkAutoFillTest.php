<?php

namespace Tests\Feature;

use App\Models\ConnectedAccount;
use App\Models\Location;
use App\Models\User;
use App\Services\Google\GoogleBusinessProfileClient;
use App\Services\Google\GoogleLocationData;
use App\Services\OAuth\FacebookOAuthClient;
use App\Services\OAuth\GoogleOAuthClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeFacebookOAuthClient;
use Tests\Fakes\FakeGoogleBusinessProfileClient;
use Tests\Fakes\FakeGoogleOAuthClient;
use Tests\TestCase;

/**
 * Phase 9 Stage 3: the Google-only side effect on
 * ConnectedAccountController@callback() that best-effort auto-fills
 * google_review_url from the newly-connected Business Profile's
 * primary location, but only when the location doesn't already have
 * one set.
 *
 * IMPORTANT, same as Stage 1/2: GoogleBusinessProfileClientImpl is
 * UNVERIFIED against real Google credentials — every test here uses
 * FakeGoogleBusinessProfileClient, bound in place of it, so nothing
 * can accidentally reach the real Business Information API.
 */
class GoogleReviewLinkAutoFillTest extends TestCase
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

    private function bindFakeGoogleOAuthClient(): FakeGoogleOAuthClient
    {
        $fake = new FakeGoogleOAuthClient();
        $this->app->instance(GoogleOAuthClient::class, $fake);

        return $fake;
    }

    private function bindFakeGoogleBusinessProfileClient(): FakeGoogleBusinessProfileClient
    {
        $fake = new FakeGoogleBusinessProfileClient();
        $this->app->instance(GoogleBusinessProfileClient::class, $fake);

        return $fake;
    }

    private function connectGoogle(): void
    {
        $this->get('/connected-accounts/google/connect');
        $this->get('/connected-accounts/google/callback?state='.session('oauth_state.google').'&code=auth-code');
    }

    public function test_connecting_google_with_a_place_id_sets_the_review_url_when_currently_unset(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $this->bindFakeGoogleOAuthClient();
        $businessProfile = $this->bindFakeGoogleBusinessProfileClient();
        $businessProfile->willReturn(new GoogleLocationData(placeId: 'ChIJabc123', locationName: 'My Business'));

        $this->connectGoogle();

        $this->assertSame(
            'https://search.google.com/local/writereview?placeid=ChIJabc123',
            $location->fresh()->google_review_url
        );
    }

    // The critical one, same rigor as everywhere else this session: a
    // manually-set google_review_url must never be silently overwritten
    // just because Google was (re)connected.
    public function test_connecting_google_does_not_overwrite_an_already_set_review_url(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $location->update(['google_review_url' => 'https://example.com/my-custom-review-link']);
        $this->actingAs($user);

        $this->bindFakeGoogleOAuthClient();
        $businessProfile = $this->bindFakeGoogleBusinessProfileClient();
        $businessProfile->willReturn(new GoogleLocationData(placeId: 'ChIJabc123', locationName: 'My Business'));

        $this->connectGoogle();

        $this->assertSame('https://example.com/my-custom-review-link', $location->fresh()->google_review_url);
    }

    public function test_connecting_google_with_no_locations_still_creates_the_connected_account(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $this->bindFakeGoogleOAuthClient();
        $businessProfile = $this->bindFakeGoogleBusinessProfileClient();
        $businessProfile->willReturn(null);

        $this->connectGoogle();

        $this->assertSame(1, ConnectedAccount::count());
        $this->assertNull($location->fresh()->google_review_url);
    }

    // The OAuth connection itself must succeed even when the
    // best-effort review-link lookup blows up entirely.
    public function test_connecting_google_when_fetch_primary_location_throws_still_creates_the_connected_account(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $this->bindFakeGoogleOAuthClient();
        $businessProfile = $this->bindFakeGoogleBusinessProfileClient();
        $businessProfile->willThrow();

        $this->connectGoogle();

        $this->assertSame(1, ConnectedAccount::count());
        $this->assertSame('google', ConnectedAccount::sole()->provider);
        $this->assertNull($location->fresh()->google_review_url);
    }

    public function test_connecting_facebook_never_calls_the_google_business_profile_client(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $this->app->instance(FacebookOAuthClient::class, new FakeFacebookOAuthClient());
        $businessProfile = $this->bindFakeGoogleBusinessProfileClient();

        $this->get('/connected-accounts/facebook/connect');
        $this->get('/connected-accounts/facebook/callback?state='.session('oauth_state.facebook').'&code=auth-code');

        $this->assertSame(1, ConnectedAccount::count());
        $this->assertSame('facebook', ConnectedAccount::sole()->provider);
        $this->assertSame([], $businessProfile->calls);
        $this->assertNull($location->fresh()->google_review_url);
    }
}
