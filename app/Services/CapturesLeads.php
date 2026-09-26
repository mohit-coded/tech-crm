<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Pipeline;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Contact + its initial Opportunity for a newly captured
 * lead, in the location's default pipeline (falling back to its first
 * pipeline if none is marked default), placed on that pipeline's first
 * stage.
 *
 * Extracted from FunnelPublicController@store (Phase 3) the moment a
 * second real caller — FacebookWebhookController (Phase 9 Stage 2) —
 * needed the exact same pipeline-lookup-and-Opportunity-creation logic.
 * Two genuine, already-existing call sites doing identical
 * multi-step domain logic is a real duplication problem worth fixing
 * now, not a hypothetical future one: without this, a future change to
 * the fallback-pipeline lookup (say) would need to be remembered and
 * applied in two places, with nothing to stop them quietly drifting
 * apart.
 */
class CapturesLeads
{
    /**
     * $funnelId records which Funnel (if any) the lead came through —
     * passed by FunnelPublicController, deliberately omitted by
     * FacebookWebhookController (a Facebook lead has no funnel).
     */
    public function capture(int $locationId, ?string $name, ?string $email, ?string $phone, string $source, ?int $funnelId = null): Contact
    {
        return DB::transaction(function () use ($locationId, $name, $email, $phone, $source, $funnelId) {
            // No authenticated user on either of this service's current
            // callers (a public funnel form, a Facebook webhook), so
            // BelongsToLocation's creating() auto-fill has nothing to key
            // off — location_id must be set explicitly, same reasoning as
            // registration's default Pipeline
            // (RegisteredUserController@store).
            $contact = Contact::create([
                'location_id' => $locationId,
                'funnel_id' => $funnelId,
                // first_name is a NOT NULL column. FunnelPublicController's
                // own form validation guarantees $name is never null for
                // that caller, but a Facebook Lead Ad form can omit a name
                // field entirely — falling back to email, then phone, then
                // a generic label keeps this from failing the insert
                // rather than erroring just because one field wasn't
                // collected.
                'first_name' => $name ?? $email ?? $phone ?? 'Unknown Lead',
                'email' => $email,
                'phone' => $phone,
            ]);

            // Pipeline is BelongsToLocation-scoped, but that scope only
            // activates for an authenticated user, which neither caller
            // has — the location_id filter here is what actually keeps
            // this tenant-safe.
            $pipeline = Pipeline::where('location_id', $locationId)
                ->where('is_default', true)
                ->first()
                ?? Pipeline::where('location_id', $locationId)->first();

            $stage = $pipeline?->stages()->first();

            abort_if(! $pipeline || ! $stage, 500, 'Location has no pipeline to receive leads.');

            Opportunity::create([
                'location_id' => $locationId,
                'contact_id' => $contact->id,
                'pipeline_id' => $pipeline->id,
                'pipeline_stage_id' => $stage->id,
                'name' => $contact->first_name,
                'status' => 'open',
                'source' => $source,
            ]);

            return $contact;
        });
    }
}
