<?php

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\Funnel;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunnelControllerTest extends TestCase
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

    public function test_index_only_lists_funnels_from_the_authenticated_users_location(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB] = $this->makeUserWithLocation();

        $this->actingAs($userA);
        Funnel::factory()->create(['name' => 'Alice Funnel', 'slug' => 'alice-funnel']);

        $this->actingAs($userB);
        Funnel::factory()->create(['name' => 'Bob Funnel', 'slug' => 'bob-funnel']);

        $this->actingAs($userA);
        $response = $this->get('/funnels');

        $response->assertOk();
        $response->assertSee('Alice Funnel');
        $response->assertDontSee('Bob Funnel');
    }

    public function test_store_creates_a_funnel_scoped_to_the_authenticated_users_location_via_the_trait(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $response = $this->post('/funnels', [
            'name' => 'Spring Promo',
            'slug' => 'spring-promo',
            'headline' => 'Get 20% Off',
            'subheadline' => 'Limited time only',
            'button_text' => 'Claim Offer',
            'is_published' => '1',
        ]);

        $response->assertRedirect(route('funnels.index'));

        $funnel = Funnel::sole();
        $this->assertSame($location->id, $funnel->location_id);
        $this->assertSame('Spring Promo', $funnel->name);
        $this->assertSame('spring-promo', $funnel->slug);
        $this->assertTrue($funnel->is_published);
    }

    public function test_cannot_view_edit_form_for_a_funnel_from_another_location(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB] = $this->makeUserWithLocation();

        $this->actingAs($userB);
        $funnelB = Funnel::factory()->create(['slug' => 'b-funnel']);

        $this->actingAs($userA);
        $response = $this->get("/funnels/{$funnelB->id}/edit");

        $response->assertNotFound();
    }

    public function test_cannot_update_a_funnel_from_another_location(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB] = $this->makeUserWithLocation();

        $this->actingAs($userB);
        $funnelB = Funnel::factory()->create(['name' => 'Bob Funnel', 'slug' => 'b-funnel']);

        $this->actingAs($userA);
        $response = $this->put("/funnels/{$funnelB->id}", [
            'name' => 'Hacked',
            'slug' => 'b-funnel',
            'headline' => 'Hacked',
            'button_text' => 'Claim Offer',
        ]);

        $response->assertNotFound();
        $this->assertSame('Bob Funnel', $funnelB->fresh()->name);
    }

    public function test_cannot_delete_a_funnel_from_another_location(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB] = $this->makeUserWithLocation();

        $this->actingAs($userB);
        $funnelB = Funnel::factory()->create(['slug' => 'b-funnel']);

        $this->actingAs($userA);
        $response = $this->delete("/funnels/{$funnelB->id}");

        $response->assertNotFound();
        $this->assertNotNull($funnelB->fresh());
    }

    public function test_can_assign_a_calendar_from_the_same_location_to_a_funnel(): void
    {
        [$user] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $calendar = Calendar::factory()->create(['name' => 'Consultations']);
        $funnel = Funnel::factory()->create(['name' => 'Spring Promo', 'slug' => 'spring-promo']);

        $response = $this->put("/funnels/{$funnel->id}", [
            'name' => $funnel->name,
            'slug' => $funnel->slug,
            'headline' => $funnel->headline,
            'button_text' => $funnel->button_text,
            'calendar_id' => $calendar->id,
        ]);

        $response->assertRedirect(route('funnels.index'));
        $this->assertSame($calendar->id, $funnel->fresh()->calendar_id);
    }

    // Same cross-tenant-smuggling pattern as the AvailabilityRule test on
    // CalendarControllerTest: attempting to point your own funnel at a
    // calendar that belongs to a different location must be rejected, not
    // silently accepted.
    public function test_cannot_assign_another_locations_calendar_to_own_funnel(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB] = $this->makeUserWithLocation();

        $this->actingAs($userB);
        $calendarB = Calendar::factory()->create();

        $this->actingAs($userA);
        $funnelA = Funnel::factory()->create(['name' => 'Spring Promo', 'slug' => 'spring-promo']);

        $response = $this->put("/funnels/{$funnelA->id}", [
            'name' => $funnelA->name,
            'slug' => $funnelA->slug,
            'headline' => $funnelA->headline,
            'button_text' => $funnelA->button_text,
            'calendar_id' => $calendarB->id,
        ]);

        $response->assertSessionHasErrors('calendar_id');
        $this->assertNull($funnelA->fresh()->calendar_id);
    }
}
