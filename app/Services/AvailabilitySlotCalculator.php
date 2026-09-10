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

        $bookedIntervals = $this->bookedIntervals($calendar, $timezone);

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
     * All non-cancelled appointments on this calendar, converted into the
     * calendar's own timezone. Not date-filtered at the query level (this
     * is a foundational implementation prioritizing correctness over query
     * scale) — the overlap check below only ever matters for appointments
     * that actually fall near the requested day.
     *
     * @return list<array{start: Carbon, end: Carbon}>
     */
    private function bookedIntervals(Calendar $calendar, string $timezone): array
    {
        return $calendar->appointments()
            ->where('status', '!=', 'cancelled')
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
