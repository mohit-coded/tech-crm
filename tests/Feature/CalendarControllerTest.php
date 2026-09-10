<?php

namespace Tests\Feature;

use App\Models\AvailabilityRule;
use App\Models\Calendar;
use App\Models\Location;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarControllerTest extends TestCase
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

    public function test_index_only_lists_calendars_from_the_authenticated_users_location(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB] = $this->makeUserWithLocation();

        $this->actingAs($userA);
        Calendar::factory()->create(['name' => 'Alice Calendar']);

        $this->actingAs($userB);
        Calendar::factory()->create(['name' => 'Bob Calendar']);

        $this->actingAs($userA);
        $response = $this->get('/calendars');

        $response->assertOk();
        $response->assertSee('Alice Calendar');
        $response->assertDontSee('Bob Calendar');
    }

    public function test_store_creates_a_calendar_scoped_to_the_authenticated_users_location_via_the_trait(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $response = $this->post('/calendars', [
            'name' => 'Consultations',
            'duration_minutes' => 45,
            'timezone' => 'America/New_York',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('calendars.index'));

        $calendar = Calendar::sole();
        $this->assertSame($location->id, $calendar->location_id);
        $this->assertSame('Consultations', $calendar->name);
        $this->assertSame(45, $calendar->duration_minutes);
        $this->assertTrue($calendar->is_active);
    }

    public function test_edit_page_shows_existing_availability_rules(): void
    {
        [$user] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $calendar = Calendar::factory()->create(['name' => 'Consultations']);
        AvailabilityRule::factory()->create([
            'calendar_id' => $calendar->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $response = $this->get("/calendars/{$calendar->id}/edit");

        $response->assertOk();
        $response->assertSee('09:00');
        $response->assertSee('17:00');
    }

    public function test_update_can_add_a_new_availability_rule(): void
    {
        [$user] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $calendar = Calendar::factory()->create();

        $response = $this->put("/calendars/{$calendar->id}", [
            'name' => $calendar->name,
            'duration_minutes' => $calendar->duration_minutes,
            'timezone' => '',
            'new_rules' => [
                ['day_of_week' => '1', 'start_time' => '09:00', 'end_time' => '17:00'],
                ['day_of_week' => '', 'start_time' => '', 'end_time' => ''],
            ],
        ]);

        $response->assertRedirect(route('calendars.edit', $calendar));

        $rule = AvailabilityRule::where('calendar_id', $calendar->id)->sole();
        $this->assertSame(1, $rule->day_of_week);
        $this->assertSame('09:00', Carbon::parse($rule->start_time)->format('H:i'));
        $this->assertSame('17:00', Carbon::parse($rule->end_time)->format('H:i'));
    }

    public function test_update_can_remove_an_availability_rule(): void
    {
        [$user] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $calendar = Calendar::factory()->create();
        $rule = AvailabilityRule::factory()->create(['calendar_id' => $calendar->id]);

        $response = $this->put("/calendars/{$calendar->id}", [
            'name' => $calendar->name,
            'duration_minutes' => $calendar->duration_minutes,
            'timezone' => '',
            'rules' => [
                $rule->id => [
                    'day_of_week' => $rule->day_of_week,
                    'start_time' => $rule->start_time,
                    'end_time' => $rule->end_time,
                    'remove' => '1',
                ],
            ],
        ]);

        $response->assertRedirect(route('calendars.edit', $calendar));
        $this->assertNull(AvailabilityRule::find($rule->id));
    }

    public function test_cannot_view_edit_form_for_a_calendar_from_another_location(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB] = $this->makeUserWithLocation();

        $this->actingAs($userB);
        $calendarB = Calendar::factory()->create();

        $this->actingAs($userA);
        $response = $this->get("/calendars/{$calendarB->id}/edit");

        $response->assertNotFound();
    }

    public function test_cannot_update_a_calendar_from_another_location(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB] = $this->makeUserWithLocation();

        $this->actingAs($userB);
        $calendarB = Calendar::factory()->create(['name' => 'Bob Calendar']);

        $this->actingAs($userA);
        $response = $this->put("/calendars/{$calendarB->id}", [
            'name' => 'Hacked',
            'duration_minutes' => 30,
        ]);

        $response->assertNotFound();
        $this->assertSame('Bob Calendar', $calendarB->fresh()->name);
    }

    public function test_cannot_delete_a_calendar_from_another_location(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB] = $this->makeUserWithLocation();

        $this->actingAs($userB);
        $calendarB = Calendar::factory()->create();

        $this->actingAs($userA);
        $response = $this->delete("/calendars/{$calendarB->id}");

        $response->assertNotFound();
        $this->assertNotNull($calendarB->fresh());
    }

    public function test_cannot_modify_another_locations_availability_rule_by_id_through_own_calendar(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB] = $this->makeUserWithLocation();

        $this->actingAs($userB);
        $calendarB = Calendar::factory()->create();
        $ruleB = AvailabilityRule::factory()->create([
            'calendar_id' => $calendarB->id,
            'day_of_week' => 2,
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        $this->actingAs($userA);
        $calendarA = Calendar::factory()->create();

        // Attempt to smuggle userB's rule id into userA's own (legitimate)
        // calendar update — it doesn't belong to calendarA's
        // availabilityRules() relation, so it must be silently ignored,
        // not updated or deleted.
        $response = $this->put("/calendars/{$calendarA->id}", [
            'name' => $calendarA->name,
            'duration_minutes' => $calendarA->duration_minutes,
            'rules' => [
                $ruleB->id => [
                    'day_of_week' => 5,
                    'start_time' => '01:00',
                    'end_time' => '02:00',
                    'remove' => '',
                ],
            ],
        ]);

        $response->assertRedirect(route('calendars.edit', $calendarA));

        $ruleB->refresh();
        $this->assertSame(2, $ruleB->day_of_week);
        $this->assertSame('10:00', Carbon::parse($ruleB->start_time)->format('H:i'));
        $this->assertSame('11:00', Carbon::parse($ruleB->end_time)->format('H:i'));
        $this->assertSame($calendarB->id, $ruleB->calendar_id);
    }
}
