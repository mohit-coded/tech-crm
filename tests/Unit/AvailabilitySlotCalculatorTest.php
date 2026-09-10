<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\Calendar;
use App\Models\Contact;
use App\Models\Location;
use App\Services\AvailabilitySlotCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises AvailabilitySlotCalculator directly — no HTTP requests, no
 * controllers, no authenticated user. Uses Tests\TestCase + RefreshDatabase
 * only because the service reads Calendar/AvailabilityRule/Appointment
 * through Eloquent relations; nothing here touches a route.
 */
class AvailabilitySlotCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeCalendar(array $attributes = [], array $locationAttributes = []): Calendar
    {
        $location = Location::factory()->create($locationAttributes);

        return Calendar::factory()->create(array_merge([
            'location_id' => $location->id,
            'duration_minutes' => 30,
            'timezone' => 'UTC',
        ], $attributes));
    }

    private function makeAppointment(Calendar $calendar, string $startsAt, string $endsAt, string $status = 'booked'): Appointment
    {
        $contact = Contact::factory()->create(['location_id' => $calendar->location_id]);

        return Appointment::factory()->create([
            'location_id' => $calendar->location_id,
            'calendar_id' => $calendar->id,
            'contact_id' => $contact->id,
            'starts_at' => Carbon::parse($startsAt, 'UTC'),
            'ends_at' => Carbon::parse($endsAt, 'UTC'),
            'status' => $status,
        ]);
    }

    /**
     * @return list<string>
     */
    private function startTimes(array $slots): array
    {
        return array_map(fn (array $slot) => $slot['start']->format('H:i'), $slots);
    }

    // Scenario: a single availability rule, no existing appointments ->
    // correct full list of slots.
    public function test_single_rule_with_no_appointments_returns_the_full_list_of_slots(): void
    {
        $calendar = $this->makeCalendar(['duration_minutes' => 30]);
        $date = Carbon::parse('2026-10-05', 'UTC');

        $calendar->availabilityRules()->create([
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        $slots = (new AvailabilitySlotCalculator())->getAvailableSlots($calendar, $date);

        $this->assertSame(['09:00', '09:30', '10:00', '10:30'], $this->startTimes($slots));
        $this->assertSame(
            ['09:30', '10:00', '10:30', '11:00'],
            array_map(fn (array $slot) => $slot['end']->format('H:i'), $slots)
        );
    }

    // Scenario: multiple rules on the same day (9-12 and 13-17) -> slots
    // from both windows, correctly separated (no slot bridges the gap).
    public function test_multiple_rules_on_the_same_day_produce_correctly_separated_slot_groups(): void
    {
        $calendar = $this->makeCalendar(['duration_minutes' => 60]);
        $date = Carbon::parse('2026-10-06', 'UTC');

        $calendar->availabilityRules()->create([
            'day_of_week' => $date->dayOfWeek, 'start_time' => '09:00', 'end_time' => '12:00',
        ]);
        $calendar->availabilityRules()->create([
            'day_of_week' => $date->dayOfWeek, 'start_time' => '13:00', 'end_time' => '17:00',
        ]);

        $slots = (new AvailabilitySlotCalculator())->getAvailableSlots($calendar, $date);

        $this->assertSame(
            ['09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00'],
            $this->startTimes($slots)
        );
    }

    // Scenario: an existing appointment in the middle of the day -> slots
    // overlapping it are excluded, slots before/after are not. Also checks
    // that a cancelled appointment does NOT block a slot (status filter).
    public function test_existing_appointment_excludes_only_the_slots_it_overlaps(): void
    {
        $calendar = $this->makeCalendar(['duration_minutes' => 30]);
        $date = Carbon::parse('2026-10-07', 'UTC');

        $calendar->availabilityRules()->create([
            'day_of_week' => $date->dayOfWeek, 'start_time' => '09:00', 'end_time' => '12:00',
        ]);

        $this->makeAppointment($calendar, '2026-10-07 10:00:00', '2026-10-07 10:30:00');
        // Cancelled — must not block 11:00-11:30.
        $this->makeAppointment($calendar, '2026-10-07 11:00:00', '2026-10-07 11:30:00', 'cancelled');

        $slots = (new AvailabilitySlotCalculator())->getAvailableSlots($calendar, $date);
        $starts = $this->startTimes($slots);

        $this->assertSame(['09:00', '09:30', '10:30', '11:00', '11:30'], $starts);
        $this->assertNotContains('10:00', $starts);
    }

    // Scenario: a slot that would partially overlap an existing appointment
    // (not an exact match) -> still correctly excluded.
    public function test_partial_overlap_not_aligned_to_slot_boundaries_is_still_excluded(): void
    {
        $calendar = $this->makeCalendar(['duration_minutes' => 60]);
        $date = Carbon::parse('2026-10-08', 'UTC');

        $calendar->availabilityRules()->create([
            'day_of_week' => $date->dayOfWeek, 'start_time' => '09:00', 'end_time' => '12:00',
        ]);

        // Overlaps both the 09:00-10:00 and 10:00-11:00 candidate slots
        // without exactly matching either one.
        $this->makeAppointment($calendar, '2026-10-08 09:30:00', '2026-10-08 10:30:00');

        $slots = (new AvailabilitySlotCalculator())->getAvailableSlots($calendar, $date);

        $this->assertSame(['11:00'], $this->startTimes($slots));
    }

    // Scenario: the last possible slot in a window that exactly fits ->
    // included.
    public function test_last_slot_that_exactly_fits_the_window_is_included(): void
    {
        $calendar = $this->makeCalendar(['duration_minutes' => 30]);
        $date = Carbon::parse('2026-10-09', 'UTC');

        $calendar->availabilityRules()->create([
            'day_of_week' => $date->dayOfWeek, 'start_time' => '09:00', 'end_time' => '10:00',
        ]);

        $slots = (new AvailabilitySlotCalculator())->getAvailableSlots($calendar, $date);

        $this->assertSame(['09:00', '09:30'], $this->startTimes($slots));
        $this->assertSame('10:00', $slots[1]['end']->format('H:i'));
    }

    // Scenario: a slot that would extend past end_time -> excluded (not
    // just truncated).
    public function test_a_slot_that_would_extend_past_end_time_is_not_generated(): void
    {
        $calendar = $this->makeCalendar(['duration_minutes' => 40]);
        $date = Carbon::parse('2026-10-10', 'UTC');

        $calendar->availabilityRules()->create([
            'day_of_week' => $date->dayOfWeek, 'start_time' => '09:00', 'end_time' => '10:00',
        ]);

        $slots = (new AvailabilitySlotCalculator())->getAvailableSlots($calendar, $date);

        // A second slot would run 09:40-10:20, past the 10:00 end_time.
        $this->assertCount(1, $slots);
        $this->assertSame('09:00', $slots[0]['start']->format('H:i'));
        $this->assertSame('09:40', $slots[0]['end']->format('H:i'));
    }

    // Scenario: requesting today's date with some slots already in the
    // past -> past slots excluded, future slots included.
    public function test_requesting_today_excludes_already_past_slots(): void
    {
        $calendar = $this->makeCalendar(['duration_minutes' => 30]);

        Carbon::setTestNow(Carbon::parse('2026-10-11 10:15:00', 'UTC'));
        $today = Carbon::parse('2026-10-11', 'UTC');

        $calendar->availabilityRules()->create([
            'day_of_week' => $today->dayOfWeek, 'start_time' => '09:00', 'end_time' => '12:00',
        ]);

        $slots = (new AvailabilitySlotCalculator())->getAvailableSlots($calendar, $today);

        $this->assertSame(['10:30', '11:00', '11:30'], $this->startTimes($slots));
    }

    // Scenario: a calendar with no rules for the requested day -> empty
    // array, no error.
    public function test_calendar_with_no_rules_for_the_requested_day_returns_empty_array(): void
    {
        $calendar = $this->makeCalendar();
        $date = Carbon::parse('2026-10-12', 'UTC');

        // A rule exists, just not for this day of week.
        $calendar->availabilityRules()->create([
            'day_of_week' => ($date->dayOfWeek + 1) % 7,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $slots = (new AvailabilitySlotCalculator())->getAvailableSlots($calendar, $date);

        $this->assertSame([], $slots);
    }

    // Scenario: DST transition date (clocks spring forward in the
    // calendar's timezone) -> slots still correctly bounded within the
    // rule's local start/end time, not shifted by the DST change.
    public function test_dst_spring_forward_day_still_bounds_slots_to_the_rules_local_times(): void
    {
        $calendar = $this->makeCalendar(['duration_minutes' => 30, 'timezone' => 'America/New_York']);

        $date = Carbon::parse('second sunday of march 2026', 'America/New_York')->startOfDay();

        // Sanity check: this really is a 23-hour spring-forward day in this
        // timezone (guards against the relative-date string above ever
        // resolving to the wrong day).
        $this->assertEqualsWithDelta(23, $date->diffInHours($date->copy()->addDay()), 0.0);

        $calendar->availabilityRules()->create([
            'day_of_week' => $date->dayOfWeek, 'start_time' => '09:00', 'end_time' => '11:00',
        ]);

        $slots = (new AvailabilitySlotCalculator())->getAvailableSlots($calendar, $date);

        $this->assertSame(['09:00', '09:30', '10:00', '10:30'], $this->startTimes($slots));
        $this->assertSame('11:00', $slots[3]['end']->format('H:i'));

        foreach ($slots as $slot) {
            $this->assertSame('America/New_York', $slot['start']->timezoneName);
        }
    }

    // Bonus, beyond the required scenarios: timezone fallback chain
    // (calendar -> location -> UTC), which the algorithm spec calls for
    // explicitly.
    public function test_falls_back_to_the_locations_timezone_when_the_calendar_has_none(): void
    {
        $calendar = $this->makeCalendar(
            ['timezone' => null, 'duration_minutes' => 30],
            ['timezone' => 'America/Chicago']
        );
        $date = Carbon::parse('2026-10-13', 'UTC');

        $calendar->availabilityRules()->create([
            'day_of_week' => $date->dayOfWeek, 'start_time' => '09:00', 'end_time' => '10:00',
        ]);

        $slots = (new AvailabilitySlotCalculator())->getAvailableSlots($calendar, $date);

        $this->assertNotEmpty($slots);
        $this->assertSame('America/Chicago', $slots[0]['start']->timezoneName);
        $this->assertSame('09:00', $slots[0]['start']->format('H:i'));
    }

    // The final `?? 'UTC'` fallback in AvailabilitySlotCalculator::resolveTimezone()
    // is defensive: `locations.timezone` is NOT NULL DEFAULT 'UTC' and
    // `calendars.location_id` is required, so a Calendar can never actually
    // reach it today. Rather than fabricate an impossible state to reach
    // the last branch, this documents why it isn't (and can't be) tested.
}
