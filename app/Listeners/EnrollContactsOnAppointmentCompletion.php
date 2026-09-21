<?php

namespace App\Listeners;

use App\Events\AppointmentCompleted;
use App\Models\Campaign;
use App\Models\CampaignEnrollment;
use App\Models\Contact;
use App\Services\EnrollsContacts;

/**
 * Auto-enrollment on appointment completion (Phase 7 Stage 1) — the
 * same shape as EnrollContactsOnStageEntry, but the match is a single
 * fixed trigger_event string ('appointment_completed') rather than a
 * name built per pipeline stage, since there's no stage involved here.
 */
class EnrollContactsOnAppointmentCompletion
{
    public function __construct(private EnrollsContacts $enrollsContacts)
    {
    }

    public function handle(AppointmentCompleted $event): void
    {
        $appointment = $event->appointment;

        // Explicit location_id filter via withoutGlobalScopes(), not a
        // reliance on BelongsToLocation's scope — same reasoning as
        // EnrollContactsOnStageEntry: this listener may run with no
        // authenticated user.
        $campaigns = Campaign::withoutGlobalScopes()
            ->where('location_id', $appointment->location_id)
            ->where('is_active', true)
            ->where('trigger_event', 'appointment_completed')
            ->get();

        if ($campaigns->isEmpty()) {
            return;
        }

        $contact = Contact::withoutGlobalScopes()->find($appointment->contact_id);

        if (! $contact) {
            return;
        }

        foreach ($campaigns as $campaign) {
            // Idempotency guard: same reasoning as
            // EnrollContactsOnStageEntry — without this, the same
            // contact could be double-enrolled if this event ever
            // fires twice.
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
