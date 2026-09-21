<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Calendar;
use App\Models\Campaign;
use App\Models\CampaignEnrollment;
use App\Models\Contact;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7 Stage 1: the appointment-completion side of the trigger
 * engine. Mirrors CampaignTriggerTest's shape exactly, but the match is
 * a single fixed trigger_event string ('appointment_completed') instead
 * of a per-stage name.
 */
class AppointmentCompletionTriggerTest extends TestCase
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

    private function makeConfirmedAppointment(Location $location, Contact $contact): Appointment
    {
        $calendar = Calendar::factory()->create(['location_id' => $location->id]);

        return Appointment::factory()->create([
            'location_id' => $location->id,
            'calendar_id' => $calendar->id,
            'contact_id' => $contact->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_active_campaign_matching_appointment_completed_auto_enrolls_the_appointments_contact(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $contact = Contact::factory()->create(['location_id' => $location->id]);
        $appointment = $this->makeConfirmedAppointment($location, $contact);

        $campaign = Campaign::factory()->create([
            'location_id' => $location->id,
            'trigger_event' => 'appointment_completed',
            'is_active' => true,
        ]);

        $response = $this->post("/appointments/{$appointment->id}/complete");
        $response->assertRedirect(route('appointments.index'));

        $enrollment = CampaignEnrollment::sole();
        $this->assertSame($campaign->id, $enrollment->campaign_id);
        $this->assertSame($contact->id, $enrollment->contact_id);
        $this->assertSame($location->id, $enrollment->location_id);
        $this->assertSame('active', $enrollment->status);
    }

    // The critical one: without the idempotency guard, the same contact
    // could be double-enrolled if this event ever fires twice.
    public function test_does_not_double_enroll_a_contact_that_already_has_an_active_enrollment_for_the_matching_campaign(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $contact = Contact::factory()->create(['location_id' => $location->id]);
        $appointment = $this->makeConfirmedAppointment($location, $contact);

        $campaign = Campaign::factory()->create([
            'location_id' => $location->id,
            'trigger_event' => 'appointment_completed',
            'is_active' => true,
        ]);

        $existingEnrollment = CampaignEnrollment::create([
            'location_id' => $location->id,
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => 'active',
        ]);

        $response = $this->post("/appointments/{$appointment->id}/complete");
        $response->assertRedirect(route('appointments.index'));

        $this->assertSame(1, CampaignEnrollment::count());
        $this->assertSame($existingEnrollment->id, CampaignEnrollment::sole()->id);
    }

    public function test_a_campaign_in_a_different_location_is_never_triggered_even_with_a_matching_trigger_event(): void
    {
        [$userA, $locationA] = $this->makeUserWithLocation();
        [, $locationB] = $this->makeUserWithLocation();

        $this->actingAs($userA);
        $contactA = Contact::factory()->create(['location_id' => $locationA->id]);
        $appointmentA = $this->makeConfirmedAppointment($locationA, $contactA);

        // Same trigger_event string, exact match — but a different location.
        Campaign::factory()->create([
            'location_id' => $locationB->id,
            'trigger_event' => 'appointment_completed',
            'is_active' => true,
        ]);

        $response = $this->post("/appointments/{$appointmentA->id}/complete");
        $response->assertRedirect(route('appointments.index'));

        $this->assertSame(0, CampaignEnrollment::count());
    }

    public function test_an_inactive_campaign_with_a_matching_trigger_event_does_not_enroll_anyone(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $contact = Contact::factory()->create(['location_id' => $location->id]);
        $appointment = $this->makeConfirmedAppointment($location, $contact);

        Campaign::factory()->create([
            'location_id' => $location->id,
            'trigger_event' => 'appointment_completed',
            'is_active' => false,
        ]);

        $response = $this->post("/appointments/{$appointment->id}/complete");
        $response->assertRedirect(route('appointments.index'));

        $this->assertSame(0, CampaignEnrollment::count());
    }
}
