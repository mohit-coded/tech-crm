<?php

namespace App\Services;

use App\Jobs\SendCampaignStep;
use App\Models\Campaign;
use App\Models\CampaignEnrollment;
use App\Models\Contact;

/**
 * Stage 2 of Campaigns: nothing calls enroll() automatically yet (no
 * trigger/auto-enrollment — that's Stage 3), but once something does,
 * it may run from a queued job or event listener with no authenticated
 * user, so location_id is always set explicitly here, never via
 * BelongsToLocation's creating() auto-fill.
 */
class EnrollsContacts
{
    public function enroll(Contact $contact, Campaign $campaign): CampaignEnrollment
    {
        $enrollment = CampaignEnrollment::create([
            'location_id' => $campaign->location_id,
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => 'active',
        ]);

        $firstStep = $campaign->steps()->first();

        if ($firstStep) {
            SendCampaignStep::dispatch($enrollment, $firstStep)
                ->delay(now()->addMinutes($firstStep->delay_minutes));
        }

        return $enrollment;
    }

    public function cancel(CampaignEnrollment $enrollment): void
    {
        $enrollment->update(['status' => 'cancelled']);
    }
}
