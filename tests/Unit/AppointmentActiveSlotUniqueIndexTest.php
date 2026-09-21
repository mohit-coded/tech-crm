<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\Calendar;
use App\Models\Contact;
use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The database-level backstop for the "nothing exists yet to lock"
 * double-booking race (see the migration's own docblock): even with a
 * lockForUpdate() check in the booking flow, two concurrent first-time
 * bookings for the same empty slot can both see "nothing to lock" and
 * both proceed — only a unique constraint at the database itself can
 * reject the loser. This bypasses the booking flow entirely
 * (Appointment::create() called directly, twice) to prove the
 * constraint itself works, independent of any application-level guard.
 */
class AppointmentActiveSlotUniqueIndexTest extends TestCase
{
    use RefreshDatabase;

    private function makeCalendarAndContacts(): array
    {
        $location = Location::factory()->create();
        $calendar = Calendar::factory()->create(['location_id' => $location->id]);
        $contactA = Contact::factory()->create(['location_id' => $location->id]);
        $contactB = Contact::factory()->create(['location_id' => $location->id]);

        return [$location, $calendar, $contactA, $contactB];
    }

    public function test_two_non_cancelled_appointments_for_the_same_calendar_and_start_time_violate_the_unique_index(): void
    {
        [$location, $calendar, $contactA, $contactB] = $this->makeCalendarAndContacts();
        $startsAt = Carbon::parse('2026-10-20 09:00:00', 'UTC');
        $endsAt = Carbon::parse('2026-10-20 09:30:00', 'UTC');

        Appointment::create([
            'location_id' => $location->id,
            'calendar_id' => $calendar->id,
            'contact_id' => $contactA->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'requested',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        Appointment::create([
            'location_id' => $location->id,
            'calendar_id' => $calendar->id,
            'contact_id' => $contactB->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'requested',
        ]);
    }

    // Different non-cancelled statuses for the same slot must ALSO
    // collide — this is exactly the case a plain (calendar_id,
    // starts_at, status) index would wrongly allow (see the migration's
    // docblock on why that's not equivalent), so it's worth proving
    // directly rather than just asserting it in a comment.
    public function test_a_requested_and_a_confirmed_appointment_for_the_same_slot_also_collide(): void
    {
        [$location, $calendar, $contactA, $contactB] = $this->makeCalendarAndContacts();
        $startsAt = Carbon::parse('2026-10-21 09:00:00', 'UTC');
        $endsAt = Carbon::parse('2026-10-21 09:30:00', 'UTC');

        Appointment::create([
            'location_id' => $location->id,
            'calendar_id' => $calendar->id,
            'contact_id' => $contactA->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'requested',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        Appointment::create([
            'location_id' => $location->id,
            'calendar_id' => $calendar->id,
            'contact_id' => $contactB->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'confirmed',
        ]);
    }

    // A cancelled appointment must NOT block reusing its old slot — the
    // entire point of the generated-column emulation over a plain
    // unique index.
    public function test_a_cancelled_appointment_does_not_block_a_new_booking_for_the_same_slot(): void
    {
        [$location, $calendar, $contactA, $contactB] = $this->makeCalendarAndContacts();
        $startsAt = Carbon::parse('2026-10-22 09:00:00', 'UTC');
        $endsAt = Carbon::parse('2026-10-22 09:30:00', 'UTC');

        Appointment::create([
            'location_id' => $location->id,
            'calendar_id' => $calendar->id,
            'contact_id' => $contactA->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'cancelled',
        ]);

        $newAppointment = Appointment::create([
            'location_id' => $location->id,
            'calendar_id' => $calendar->id,
            'contact_id' => $contactB->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'requested',
        ]);

        $this->assertNotNull($newAppointment->fresh());
        $this->assertSame(2, Appointment::withoutGlobalScopes()->count());
    }

    // Different calendars, or different start times on the same
    // calendar, must never collide — the index is scoped correctly, not
    // just "any two appointments ever".
    public function test_a_different_calendar_or_a_different_start_time_never_collides(): void
    {
        [$location, $calendarA, $contactA, $contactB] = $this->makeCalendarAndContacts();
        $calendarB = Calendar::factory()->create(['location_id' => $location->id]);
        $startsAt = Carbon::parse('2026-10-23 09:00:00', 'UTC');
        $endsAt = Carbon::parse('2026-10-23 09:30:00', 'UTC');

        Appointment::create([
            'location_id' => $location->id,
            'calendar_id' => $calendarA->id,
            'contact_id' => $contactA->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'requested',
        ]);

        // Same start time, different calendar.
        Appointment::create([
            'location_id' => $location->id,
            'calendar_id' => $calendarB->id,
            'contact_id' => $contactB->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'requested',
        ]);

        // Same calendar, different start time.
        Appointment::create([
            'location_id' => $location->id,
            'calendar_id' => $calendarA->id,
            'contact_id' => $contactB->id,
            'starts_at' => $startsAt->clone()->addMinutes(30),
            'ends_at' => $endsAt->clone()->addMinutes(30),
            'status' => 'requested',
        ]);

        $this->assertSame(3, Appointment::withoutGlobalScopes()->count());
    }
}
