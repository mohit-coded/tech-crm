<?php

namespace App\Listeners;

use App\Events\OpportunityStageChanged;
use App\Models\Campaign;
use App\Models\CampaignEnrollment;
use App\Models\Contact;
use App\Models\PipelineStage;
use App\Services\EnrollsContacts;

/**
 * Auto-enrollment side of the trigger engine (Phase 6 Stage 3):
 * OpportunityStageChanged fires on both a fresh Opportunity created with
 * an initial stage (Opportunity::booted()) and a later moveToStage()
 * call, so this one listener covers both paths.
 *
 * Matching is name-based, not stage-id-based: a Campaign's trigger_event
 * is a location-independent string of the form
 * "opportunity_stage:{stage name}", matched against the *name* of the
 * stage the opportunity just entered. This is consistent with the
 * existing "Booking Requested"/"Booking Confirmed" stage-lookup-by-name
 * pattern elsewhere in the app (see FunnelPublicController), not a new
 * convention.
 */
class EnrollContactsOnStageEntry
{
    public function __construct(private EnrollsContacts $enrollsContacts)
    {
    }

    public function handle(OpportunityStageChanged $event): void
    {
        $opportunity = $event->opportunity;

        // Deliberately looked up by $event->newStageId rather than
        // $opportunity->stage — moveToStage() updates pipeline_stage_id
        // via $this->update() but doesn't clear any already-cached
        // `stage` relation on that same in-memory instance (e.g. from an
        // earlier access, such as this very listener having run once
        // already for this opportunity's creation), which would silently
        // read the stage it just moved OUT of instead of the one it
        // moved into.
        $stageName = PipelineStage::find($event->newStageId)?->name;

        if (! $stageName) {
            return;
        }

        $triggerEvent = "opportunity_stage:{$stageName}";

        // Explicit location_id filter via withoutGlobalScopes(), not a
        // reliance on BelongsToLocation's scope — this listener may run
        // with no authenticated user (a queued job, a future non-HTTP
        // trigger source), same reasoning as every other "queued
        // job"/"public route" explicit-filter case in this app.
        $campaigns = Campaign::withoutGlobalScopes()
            ->where('location_id', $opportunity->location_id)
            ->where('is_active', true)
            ->where('trigger_event', $triggerEvent)
            ->get();

        if ($campaigns->isEmpty()) {
            return;
        }

        $contact = Contact::withoutGlobalScopes()->find($opportunity->contact_id);

        if (! $contact) {
            return;
        }

        foreach ($campaigns as $campaign) {
            // Idempotency guard: without this, the same lead could be
            // double-enrolled if this event ever fires twice for the
            // same transition.
            $alreadyEnrolled = CampaignEnrollment::withoutGlobalScopes()
                ->where('campaign_id', $campaign->id)
                ->where('contact_id', $contact->id)
                ->where('status', 'active')
                ->exists();

            if ($alreadyEnrolled) {
                continue;
            }

            $this->enrollsContacts->enroll($contact, $campaign);
        }
    }
}
