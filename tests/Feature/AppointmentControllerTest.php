<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Calendar;
use App\Models\Contact;
use App\Models\Location;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentControllerTest extends TestCase
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

    private function makeAppointment(Location $location, array $overrides = []): Appointment
    {
        $calendar = Calendar::factory()->create(['location_id' => $location->id]);
        $contact = Contact::factory()->create(['location_id' => $location->id]);

        return Appointment::factory()->create(array_merge([
            'location_id' => $location->id,
            'calendar_id' => $calendar->id,
            'contact_id' => $contact->id,
            'starts_at' => Carbon::parse('2026-10-05 09:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-10-05 09:30:00', 'UTC'),
            'status' => 'requested',
        ], $overrides));
    }

    public function test_index_only_lists_appointments_from_the_authenticated_users_location(): void
    {
        [$userA, $locationA] = $this->makeUserWithLocation();
        [$userB, $locationB] = $this->makeUserWithLocation();

        $this->actingAs($userA);
        $calendarA = Calendar::factory()->create(['location_id' => $locationA->id, 'name' => 'Alice Calendar']);
        $contactA = Contact::factory()->create(['location_id' => $locationA->id, 'first_name' => 'Alice']);
        Appointment::factory()->create([
            'location_id' => $locationA->id,
            'calendar_id' => $calendarA->id,
            'contact_id' => $contactA->id,
        ]);

        $this->actingAs($userB);
        $calendarB = Calendar::factory()->create(['location_id' => $locationB->id, 'name' => 'Bob Calendar']);
        $contactB = Contact::factory()->create(['location_id' => $locationB->id, 'first_name' => 'Bob']);
        Appointment::factory()->create([
            'location_id' => $locationB->id,
            'calendar_id' => $calendarB->id,
            'contact_id' => $contactB->id,
        ]);

        $this->actingAs($userA);
        $response = $this->get('/appointments');

        $response->assertOk();
        $response->assertSee('Alice');
        $response->assertDontSee('Bob');
    }

    public function test_cannot_confirm_an_appointment_from_another_location(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB, $locationB] = $this->makeUserWithLocation();

        $this->actingAs($userB);
        $appointmentB = $this->makeAppointment($locationB, ['status' => 'requested']);

        $this->actingAs($userA);
        $response = $this->post("/appointments/{$appointmentB->id}/confirm");

        $response->assertNotFound();
        $this->assertSame('requested', $appointmentB->fresh()->status);
    }

    public function test_cannot_cancel_an_appointment_from_another_location(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB, $locationB] = $this->makeUserWithLocation();

        $this->actingAs($userB);
        $appointmentB = $this->makeAppointment($locationB, ['status' => 'requested']);

        $this->actingAs($userA);
        $response = $this->post("/appointments/{$appointmentB->id}/cancel");

        $response->assertNotFound();
        $this->assertSame('requested', $appointmentB->fresh()->status);
    }

    public function test_confirm_sets_status_to_confirmed(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $appointment = $this->makeAppointment($location, ['status' => 'requested']);

        $response = $this->post("/appointments/{$appointment->id}/confirm");

        $response->assertRedirect(route('appointments.index'));
        $this->assertSame('confirmed', $appointment->fresh()->status);
    }

    public function test_cancel_sets_status_to_cancelled_and_does_not_move_any_opportunity(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $pipeline = Pipeline::factory()->create(['location_id' => $location->id, 'is_default' => true]);
        $stage = $pipeline->stages()->create(['name' => 'New Leads', 'position' => 0]);
        $confirmedStage = $pipeline->stages()->create(['name' => 'Booking Confirmed', 'position' => 1]);

        $contact = Contact::factory()->create(['location_id' => $location->id]);
        $opportunity = Opportunity::factory()->create([
            'location_id' => $location->id,
            'contact_id' => $contact->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
        ]);

        $calendar = Calendar::factory()->create(['location_id' => $location->id]);
        $appointment = Appointment::factory()->create([
            'location_id' => $location->id,
            'calendar_id' => $calendar->id,
            'contact_id' => $contact->id,
            'status' => 'requested',
        ]);

        $response = $this->post("/appointments/{$appointment->id}/cancel");

        $response->assertRedirect(route('appointments.index'));
        $this->assertSame('cancelled', $appointment->fresh()->status);
        // Cancelling never moves the stage — only confirm() does.
        $this->assertSame($stage->id, $opportunity->fresh()->pipeline_stage_id);
        $this->assertNotSame($confirmedStage->id, $opportunity->fresh()->pipeline_stage_id);
    }

    public function test_confirming_moves_the_leads_opportunity_to_booking_confirmed_stage(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $pipeline = Pipeline::factory()->create(['location_id' => $location->id, 'is_default' => true]);
        $requestedStage = $pipeline->stages()->create(['name' => 'Booking Requested', 'position' => 0]);
        $confirmedStage = $pipeline->stages()->create(['name' => 'Booking Confirmed', 'position' => 1]);

        $contact = Contact::factory()->create(['location_id' => $location->id]);
        $opportunity = Opportunity::factory()->create([
            'location_id' => $location->id,
            'contact_id' => $contact->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $requestedStage->id,
        ]);

        $calendar = Calendar::factory()->create(['location_id' => $location->id]);
        $appointment = Appointment::factory()->create([
            'location_id' => $location->id,
            'calendar_id' => $calendar->id,
            'contact_id' => $contact->id,
            'status' => 'requested',
        ]);

        $response = $this->post("/appointments/{$appointment->id}/confirm");

        $response->assertRedirect(route('appointments.index'));
        $this->assertSame('confirmed', $appointment->fresh()->status);
        $this->assertSame($confirmedStage->id, $opportunity->fresh()->pipeline_stage_id);
    }

    public function test_confirming_an_appointment_with_no_opportunity_does_not_error(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        // A Contact exists but was never captured through a funnel, so it
        // has no Opportunity at all.
        $appointment = $this->makeAppointment($location, ['status' => 'requested']);

        $response = $this->post("/appointments/{$appointment->id}/confirm");

        $response->assertRedirect(route('appointments.index'));
        $this->assertSame('confirmed', $appointment->fresh()->status);
    }

    public function test_confirming_skips_the_stage_move_when_the_pipeline_has_no_booking_confirmed_stage(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $pipeline = Pipeline::factory()->create(['location_id' => $location->id, 'is_default' => true]);
        $onlyStage = $pipeline->stages()->create(['name' => 'New Leads', 'position' => 0]);

        $contact = Contact::factory()->create(['location_id' => $location->id]);
        $opportunity = Opportunity::factory()->create([
            'location_id' => $location->id,
            'contact_id' => $contact->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $onlyStage->id,
        ]);

        $calendar = Calendar::factory()->create(['location_id' => $location->id]);
        $appointment = Appointment::factory()->create([
            'location_id' => $location->id,
            'calendar_id' => $calendar->id,
            'contact_id' => $contact->id,
            'status' => 'requested',
        ]);

        $response = $this->post("/appointments/{$appointment->id}/confirm");

        $response->assertRedirect(route('appointments.index'));
        $this->assertSame('confirmed', $appointment->fresh()->status);
        // No matching stage in this pipeline — the Opportunity is left
        // exactly where it was, not errored on.
        $this->assertSame($onlyStage->id, $opportunity->fresh()->pipeline_stage_id);
    }

    public function test_appointment_date_time_is_displayed_in_the_calendars_own_timezone_not_utc(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $calendar = Calendar::factory()->create([
            'location_id' => $location->id,
            'timezone' => 'America/New_York',
        ]);
        $contact = Contact::factory()->create(['location_id' => $location->id]);

        // 14:00 UTC is 9:00 AM in America/New_York (UTC-5, standard time
        // — January isn't in DST).
        Appointment::factory()->create([
            'location_id' => $location->id,
            'calendar_id' => $calendar->id,
            'contact_id' => $contact->id,
            'starts_at' => Carbon::parse('2026-01-05 14:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-01-05 14:30:00', 'UTC'),
            'status' => 'requested',
        ]);

        $response = $this->get('/appointments');

        $response->assertOk();
        $response->assertSee('9:00 AM');
        $response->assertDontSee('2:00 PM');
    }
}
