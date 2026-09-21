<?php

namespace App\Services;

use App\Models\AvailabilityRule;
use App\Models\Calendar;
use Carbon\Carbon;

class AvailabilitySlotCalculator
{
    /**
     * Compute the bookable slots for a calendar on a given calendar day.
     *
     * All math happens in the calendar's own timezone (falling back to its
     * location's timezone, then UTC). Only the Y-m-d portion of $date is
     * used — it's re-anchored to local midnight in that timezone, so the
     * timezone $date itself happens to carry doesn't matter.
     *
     * @return list<array{start: Carbon, end: Carbon}> chronologically
     *         ordered, in the calendar's timezone
     */
    public function getAvailableSlots(Calendar $calendar, Carbon $date): array
    {
        $timezone = $this->resolveTimezone($calendar);

        $localDate = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $date->format('Y-m-d').' 00:00:00',
            $timezone
        );

        $rules = $calendar->availabilityRules()
            ->where('day_of_week', $localDate->dayOfWeek)
            ->get();

        if ($rules->isEmpty()) {
            return [];
        }

        $bookedIntervals = $this->bookedIntervals($calendar, $timezone, $localDate);

        $now = Carbon::now($timezone);
        $isToday = $localDate->isSameDay($now);

        $slots = [];

        foreach ($rules as $rule) {
            foreach ($this->candidateSlotsForRule($rule, $localDate, $calendar->duration_minutes) as $slot) {
                if ($isToday && $slot['start']->lt($now)) {
                    continue;
                }

                if ($this->overlapsAny($slot, $bookedIntervals)) {
                    continue;
                }

                $slots[] = $slot;
            }
        }

        usort($slots, fn (array $a, array $b) => $a['start'] <=> $b['start']);

        return $slots;
    }

    private function resolveTimezone(Calendar $calendar): string
    {
        return $calendar->timezone
            ?? $calendar->location?->timezone
            ?? 'UTC';
    }

    /**
     * @return list<array{start: Carbon, end: Carbon}>
     */
    private function candidateSlotsForRule(AvailabilityRule $rule, Carbon $localDate, int $durationMinutes): array
    {
        $windowStart = $localDate->clone()->setTimeFromTimeString($rule->start_time);
        $windowEnd = $localDate->clone()->setTimeFromTimeString($rule->end_time);

        $slots = [];
        $cursor = $windowStart->clone();

        while (true) {
            $slotEnd = $cursor->clone()->addMinutes($durationMinutes);

            // Stop as soon as a candidate would extend past the window's
            // end_time — never generate a partially-fitting slot.
            if ($slotEnd->gt($windowEnd)) {
                break;
            }

            $slots[] = ['start' => $cursor->clone(), 'end' => $slotEnd];
            $cursor = $slotEnd;
        }

        return $slots;
    }

    /**
     * Non-cancelled appointments on this calendar that could plausibly
     * overlap the requested local day, converted into the calendar's own
     * timezone. Bounded at the query level to a window around
     * $localDate (previously fetched every non-cancelled appointment on
     * the whole calendar — a known, flagged trade-off, now resolved).
     *
     * The window is $localDate's local start/end, padded by a full 24
     * hours on each side and converted to UTC for the query (the column
     * is stored in UTC — see the Appointment model — so the bound must
     * be too, not left in the calendar's local timezone). 24 hours is
     * deliberately generous rather than an exact boundary: the widest
     * real-world UTC offset is +14:00 (Kiribati) to -12:00 (Baker
     * Island), so a full day's padding on each side covers every
     * possible timezone (and any DST shift within it) with room to
     * spare, without having to compute or special-case an exact offset
     * per timezone. Over-padding costs a handful of extra rows fetched;
     * under-padding risks silently excluding a genuinely overlapping
     * appointment, which is the one outcome this can't allow — so the
     * trade deliberately favors width.
     *
     * @return list<array{start: Carbon, end: Carbon}>
     */
    private function bookedIntervals(Calendar $calendar, string $timezone, Carbon $localDate): array
    {
        $windowStart = $localDate->clone()->subDay()->setTimezone('UTC');
        $windowEnd = $localDate->clone()->addDays(2)->setTimezone('UTC');

        return $calendar->appointments()
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $windowEnd)
            ->where('ends_at', '>', $windowStart)
            ->get()
            ->map(fn ($appointment) => [
                'start' => $appointment->starts_at->clone()->setTimezone($timezone),
                'end' => $appointment->ends_at->clone()->setTimezone($timezone),
            ])
            ->all();
    }

    /**
     * @param  array{start: Carbon, end: Carbon}  $slot
     * @param  list<array{start: Carbon, end: Carbon}>  $intervals
     */
    private function overlapsAny(array $slot, array $intervals): bool
    {
        foreach ($intervals as $interval) {
            if ($slot['start']->lt($interval['end']) && $slot['end']->gt($interval['start'])) {
                return true;
            }
        }

        return false;
    }
}
