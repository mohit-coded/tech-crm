<?php

namespace Database\Factories;

use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    /**
     * location_id is deliberately omitted (like ContactFactory/CalendarFactory)
     * so BelongsToLocation's creating() hook auto-fills it from the acting
     * user's current_location_id in tests that rely on that.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Campaign',
            'trigger_event' => 'opportunity_stage_changed',
            'is_active' => true,
        ];
    }
}
