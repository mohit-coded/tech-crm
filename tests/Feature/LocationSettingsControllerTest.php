<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationSettingsControllerTest extends TestCase
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

    public function test_edit_shows_the_authenticated_users_current_location_settings(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $location->update(['google_review_url' => 'https://g.page/r/example/review']);
        $this->actingAs($user);

        $response = $this->get('/settings');

        $response->assertOk();
        $response->assertSee('https://g.page/r/example/review');
    }

    public function test_update_sets_google_review_url_on_the_authenticated_users_current_location(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $response = $this->put('/settings', ['google_review_url' => 'https://g.page/r/example/review']);

        $response->assertRedirect(route('settings.location.edit'));
        $this->assertSame('https://g.page/r/example/review', $location->fresh()->google_review_url);
    }

    // There's no {location} route parameter on this controller to
    // smuggle a cross-tenant id through — edit()/update() always act on
    // Auth::user()->currentLocation directly, the same source of truth
    // BelongsToLocation itself keys off. This is structurally different
    // from every route-model-bound controller elsewhere in this app
    // (which needs a 404-on-cross-tenant-id test), but is worth proving
    // rather than just asserting by inspection: acting as user A and
    // submitting the update only ever changes location A, never
    // location B, no matter what other locations/users exist.
    public function test_update_only_ever_affects_the_authenticated_users_own_current_location(): void
    {
        [$userA, $locationA] = $this->makeUserWithLocation();
        [, $locationB] = $this->makeUserWithLocation();

        $this->actingAs($userA);
        $response = $this->put('/settings', ['google_review_url' => 'https://g.page/r/a/review']);

        $response->assertRedirect(route('settings.location.edit'));
        $this->assertSame('https://g.page/r/a/review', $locationA->fresh()->google_review_url);
        $this->assertNull($locationB->fresh()->google_review_url);
    }
}
