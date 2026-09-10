<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Calendar;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    /**
     * location_id is deliberately omitted (like ContactFactory/CalendarFactory)
     * so BelongsToLocation's creating() hook auto-fills it from the acting
     * user's current_location_id in tests that rely on that.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 day', '+1 week');

        return [
            'calendar_id' => Calendar::factory(),
            'contact_id' => Contact::factory(),
            'starts_at' => $start,
            'ends_at' => (clone $start)->modify('+30 minutes'),
            'status' => 'booked',
        ];
    }
}
