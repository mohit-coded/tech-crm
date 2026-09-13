<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignEnrollment;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignEnrollment>
 */
class CampaignEnrollmentFactory extends Factory
{
    protected $model = CampaignEnrollment::class;

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
            'campaign_id' => Campaign::factory(),
            'contact_id' => Contact::factory(),
            'current_step_id' => null,
            'status' => 'active',
        ];
    }
}
