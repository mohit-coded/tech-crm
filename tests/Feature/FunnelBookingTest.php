<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Calendar;
use App\Models\Contact;
use App\Models\Funnel;
use App\Models\Location;
use App\Models\Opportunity;
use App\Models\Pipeline;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunnelBookingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A fixed future date, always used together with a rule for its own
     * day-of-week so tests don't depend on when they happen to run.
     */
    private function bookingDate(): Carbon
    {
        return Carbon::parse('2026-10-05', 'UTC');
    }

    /**
     * @return array{0: Location, 1: Calendar}
     */
    private function makeLocationWithCalendar(array $ruleOverrides = []): array
    {
        $location = Location::factory()->create(['timezone' => 'UTC']);

        $calendar = Calendar::factory()->create([
            'location_id' => $location->id,
            'timezone' => 'UTC',
            'duration_minutes' => 30,
        ]);

        $calendar->availabilityRules()->create(array_merge([
            'day_of_week' => $this->bookingDate()->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ], $ruleOverrides));

        return [$location, $calendar];
    }

    public function test_booking_page_shows_available_slots_from_the_real_calculator(): void
    {
        [$location, $calendar] = $this->makeLocationWithCalendar();

        Funnel::factory()->create([
            'location_id' => $location->id,
            'calendar_id' => $calendar->id,
            'slug' => 'book-test',
            'is_published' => true,
        ]);

        $response = $this->get('/f/book-test/book?date='.$this->bookingDate()->format('Y-m-d'));

        $response->assertOk();
        $response->assertSee('9:00 AM');
        $response->assertSee('9:30 AM');
        $response->assertSee('10:00 AM');
        $response->assertSee('10:30 AM');
        // Window is 09:00-11:00 in 30-minute slots — 11:00 itself is not
        // a valid slot start (nothing left to fit).
        $response->assertDontSee('11:00 AM');
    }

    public function test_booking_page_shows_unavailable_message_when_funnel_has_no_calendar(): void
    {
        $location = Location::factory()->create();

        Funnel::factory()->create([
            'location_id' => $location->id,
            'calendar_id' => null,
            'slug' => 'no-calendar-funnel',
            'is_published' => true,
        ]);

        $response = $this->get('/f/no-calendar-funnel/book');

        $response->assertOk();
        $response->assertSee('Booking is not available for this offer.');
    }

    public function test_confirming_a_valid_slot_creates_exactly_one_appointment(): void
    {
        [$location, $calendar] = $this->makeLocationWithCalendar();

        Funnel::factory()->create([
            'location_id' => $location->id,
            'calendar_id' => $calendar->id,
            'slug' => 'book-test',
            'is_published' => true,
        ]);

        $response = $this->post('/f/book-test/book/confirm', [
            'date' => $this->bookingDate()->format('Y-m-d'),
            'start_time' => '09:00',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '555-1234',
        ]);

        $response->assertRedirect(route('funnels.public.book.confirmed', 'book-test'));

        $appointment = Appointment::withoutGlobalScopes()->sole();
        $this->assertSame($location->id, $appointment->location_id);
        $this->assertSame($calendar->id, $appointment->calendar_id);
        $this->assertSame('requested', $appointment->status);
        $this->assertSame('2026-10-05 09:00:00', $appointment->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-05 09:30:00', $appointment->ends_at->format('Y-m-d H:i:s'));

        $contact = Contact::withoutGlobalScopes()->sole();
        $this->assertSame($appointment->contact_id, $contact->id);
        $this->assertSame('jane@example.com', $contact->email);
    }

    public function test_confirming_a_booking_moves_the_leads_opportunity_to_the_booking_requested_stage(): void
    {
        $location = Location::factory()->create(['timezone' => 'UTC']);
        $pipeline = Pipeline::factory()->create(['location_id' => $location->id, 'is_default' => true]);
        $newLeadsStage = $pipeline->stages()->create(['name' => 'New Leads', 'position' => 0]);
        $bookingRequestedStage = $pipeline->stages()->create(['name' => 'Booking Requested', 'position' => 1]);

        $calendar = Calendar::factory()->create([
            'location_id' => $location->id,
            'timezone' => 'UTC',
            'duration_minutes' => 30,
        ]);
        $calendar->availabilityRules()->create([
            'day_of_week' => $this->bookingDate()->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        $funnel = Funnel::factory()->create([
            'location_id' => $location->id,
            'calendar_id' => $calendar->id,
            'slug' => 'book-test',
            'is_published' => true,
        ]);

        // Visitor submits the lead form first, same as any other funnel.
        $this->post('/f/book-test/submit', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '555-1234',
        ]);

        $opportunity = Opportunity::withoutGlobalScopes()->sole();
        $this->assertSame($newLeadsStage->id, $opportunity->pipeline_stage_id);

        // Then books an appointment with the same details.
        $response = $this->post('/f/book-test/book/confirm', [
            'date' => $this->bookingDate()->format('Y-m-d'),
            'start_time' => '09:00',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '555-1234',
        ]);

        $response->assertRedirect(route('funnels.public.book.confirmed', 'book-test'));
        $this->assertSame($bookingRequestedStage->id, $opportunity->fresh()->pipeline_stage_id);

        // Still exactly one Contact and one Opportunity — the booking
        // step reused the lead, it didn't duplicate it.
        $this->assertSame(1, Contact::withoutGlobalScopes()->count());
        $this->assertSame(1, Opportunity::withoutGlobalScopes()->count());
    }

    // The most important test here: a slot that looked available when the
    // page was rendered but gets taken before the visitor submits must be
    // rejected, not silently double-booked.
    public function test_booking_a_slot_taken_between_page_load_and_submission_is_rejected(): void
    {
        [$location, $calendar] = $this->makeLocationWithCalendar();

        Funnel::factory()->create([
            'location_id' => $location->id,
            'calendar_id' => $calendar->id,
            'slug' => 'book-test',
            'is_published' => true,
        ]);

        // Visitor loads the page — 09:00 is available.
        $page = $this->get('/f/book-test/book?date='.$this->bookingDate()->format('Y-m-d'));
        $page->assertSee('9:00 AM');

        // Someone else books that exact slot in the meantime.
        $otherContact = Contact::factory()->create(['location_id' => $location->id]);
        Appointment::factory()->create([
            'location_id' => $location->id,
            'calendar_id' => $calendar->id,
            'contact_id' => $otherContact->id,
            'starts_at' => $this->bookingDate()->copy()->setTime(9, 0),
            'ends_at' => $this->bookingDate()->copy()->setTime(9, 30),
            'status' => 'booked',
        ]);

        // The original visitor now submits their (stale) selection.
        $response = $this->post('/f/book-test/book/confirm', [
            'date' => $this->bookingDate()->format('Y-m-d'),
            'start_time' => '09:00',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '555-1234',
        ]);

        $response->assertRedirect(route('funnels.public.book', [
            'slug' => 'book-test',
            'date' => $this->bookingDate()->format('Y-m-d'),
        ]));
        $response->assertSessionHasErrors('start_time');

        // Only the other person's appointment exists — Jane was not booked.
        $this->assertSame(1, Appointment::withoutGlobalScopes()->count());
        $this->assertNull(Contact::withoutGlobalScopes()->where('email', 'jane@example.com')->first());
    }

    // Cross-tenant: the controller never trusts a client-supplied
    // calendar_id/location_id — it always uses the funnel's own, so even
    // an explicit attempt to smuggle another tenant's calendar in must be
    // ignored rather than honored.
    public function test_submitted_calendar_id_is_ignored_appointment_always_uses_the_funnels_own_calendar(): void
    {
        [$locationA, $calendarA] = $this->makeLocationWithCalendar();
        [$locationB, $calendarB] = $this->makeLocationWithCalendar();

        Funnel::factory()->create([
            'location_id' => $locationA->id,
            'calendar_id' => $calendarA->id,
            'slug' => 'funnel-a',
            'is_published' => true,
        ]);

        $response = $this->post('/f/funnel-a/book/confirm', [
            'date' => $this->bookingDate()->format('Y-m-d'),
            'start_time' => '09:00',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '555-1234',
            // Attempted smuggling — the controller must never read these.
            'calendar_id' => $calendarB->id,
            'location_id' => $locationB->id,
        ]);

        $response->assertRedirect(route('funnels.public.book.confirmed', 'funnel-a'));

        $appointment = Appointment::withoutGlobalScopes()->sole();
        $this->assertSame($locationA->id, $appointment->location_id);
        $this->assertSame($calendarA->id, $appointment->calendar_id);
        $this->assertNotSame($calendarB->id, $appointment->calendar_id);
    }
}
