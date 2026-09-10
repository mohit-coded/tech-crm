<?php

namespace Database\Factories;

use App\Models\Calendar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Calendar>
 */
class CalendarFactory extends Factory
{
    protected $model = Calendar::class;

    /**
     * location_id is deliberately omitted (like ContactFactory/FunnelFactory)
     * so BelongsToLocation's creating() hook auto-fills it from the acting
     * user's current_location_id in tests that rely on that.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Calendar',
            'duration_minutes' => 30,
            'timezone' => null,
            'is_active' => true,
        ];
    }
}
