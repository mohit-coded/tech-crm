<?php

namespace Tests\Feature;

use App\Events\OpportunityStageChanged;
use App\Models\Campaign;
use App\Models\CampaignEnrollment;
use App\Models\Contact;
use App\Models\Location;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Phase 6 Stage 3: the trigger engine that auto-enrolls a contact when
 * their Opportunity enters a pipeline stage matching some active
 * Campaign's trigger_event. Two things fire OpportunityStageChanged —
 * Opportunity::booted()'s created() hook (a fresh Opportunity created
 * with an initial stage) and the existing moveToStage() — and both are
 * exercised here since EnrollContactsOnStageEntry must handle both.
 */
class CampaignTriggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_opportunity_with_an_initial_stage_fires_opportunity_stage_changed(): void
    {
        // Faking only this event (rather than Event::fake()) keeps
        // Eloquent's own creating/saving model events working, same
        // reasoning as OpportunityTest's equivalent moveToStage test.
        Event::fake([OpportunityStageChanged::class]);

        $location = Location::factory()->create();
        $contact = Contact::factory()->create(['location_id' => $location->id]);
        $pipeline = Pipeline::factory()->create(['location_id' => $location->id]);
        $stage = $pipeline->stages()->create(['name' => 'New Leads', 'position' => 0]);

        $opportunity = Opportunity::factory()->create([
            'location_id' => $location->id,
            'contact_id' => $contact->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
        ]);

        Event::assertDispatched(
            OpportunityStageChanged::class,
            fn (OpportunityStageChanged $event) => $event->opportunity->is($opportunity)
                && $event->oldStageId === null
                && $event->newStageId === $stage->id
        );
    }

    public function test_active_campaign_matching_the_new_opportunitys_initial_stage_auto_enrolls_its_contact(): void
    {
        $location = Location::factory()->create();
        $contact = Contact::factory()->create(['location_id' => $location->id]);
        $pipeline = Pipeline::factory()->create(['location_id' => $location->id]);
        $stage = $pipeline->stages()->create(['name' => 'Hot Leads', 'position' => 0]);

        $campaign = Campaign::factory()->create([
            'location_id' => $location->id,
            'trigger_event' => 'opportunity_stage:Hot Leads',
            'is_active' => true,
        ]);

        Opportunity::factory()->create([
            'location_id' => $location->id,
            'contact_id' => $contact->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
        ]);

        $enrollment = CampaignEnrollment::sole();
        $this->assertSame($campaign->id, $enrollment->campaign_id);
        $this->assertSame($contact->id, $enrollment->contact_id);
        $this->assertSame($location->id, $enrollment->location_id);
        $this->assertSame('active', $enrollment->status);
    }

    // The critical one: without the idempotency guard, the same lead
    // could get double-enrolled if this event ever fires twice for the
    // same transition.
    public function test_does_not_double_enroll_a_contact_that_already_has_an_active_enrollment_for_the_matching_campaign(): void
    {
        $location = Location::factory()->create();
        $contact = Contact::factory()->create(['location_id' => $location->id]);
        $pipeline = Pipeline::factory()->create(['location_id' => $location->id]);
        $stage = $pipeline->stages()->create(['name' => 'Hot Leads', 'position' => 0]);

        $campaign = Campaign::factory()->create([
            'location_id' => $location->id,
            'trigger_event' => 'opportunity_stage:Hot Leads',
            'is_active' => true,
        ]);

        $existingEnrollment = CampaignEnrollment::create([
            'location_id' => $location->id,
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => 'active',
        ]);

        Opportunity::factory()->create([
            'location_id' => $location->id,
            'contact_id' => $contact->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
        ]);

        $this->assertSame(1, CampaignEnrollment::count());
        $this->assertSame($existingEnrollment->id, CampaignEnrollment::sole()->id);
    }

    public function test_moving_an_opportunity_to_a_matching_stage_via_move_to_stage_also_triggers_a_matching_campaign(): void
    {
        $location = Location::factory()->create();
        $contact = Contact::factory()->create(['location_id' => $location->id]);
        $pipeline = Pipeline::factory()->create(['location_id' => $location->id]);
        $oldStage = $pipeline->stages()->create(['name' => 'New Leads', 'position' => 0]);
        $newStage = $pipeline->stages()->create(['name' => 'Hot Leads', 'position' => 1]);

        $campaign = Campaign::factory()->create([
            'location_id' => $location->id,
            'trigger_event' => 'opportunity_stage:Hot Leads',
            'is_active' => true,
        ]);

        $opportunity = Opportunity::factory()->create([
            'location_id' => $location->id,
            'contact_id' => $contact->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $oldStage->id,
        ]);

        // Created in "New Leads", which nothing targets — no enrollment yet.
        $this->assertSame(0, CampaignEnrollment::count());

        $opportunity->moveToStage($newStage);

        $enrollment = CampaignEnrollment::sole();
        $this->assertSame($campaign->id, $enrollment->campaign_id);
        $this->assertSame($contact->id, $enrollment->contact_id);
    }

    public function test_a_campaign_in_a_different_location_is_never_triggered_even_with_a_matching_trigger_event(): void
    {
        $locationA = Location::factory()->create();
        $locationB = Location::factory()->create();

        $contactA = Contact::factory()->create(['location_id' => $locationA->id]);
        $pipelineA = Pipeline::factory()->create(['location_id' => $locationA->id]);
        $stageA = $pipelineA->stages()->create(['name' => 'Hot Leads', 'position' => 0]);

        // Same trigger_event string, exact match — but a different location.
        Campaign::factory()->create([
            'location_id' => $locationB->id,
            'trigger_event' => 'opportunity_stage:Hot Leads',
            'is_active' => true,
        ]);

        Opportunity::factory()->create([
            'location_id' => $locationA->id,
            'contact_id' => $contactA->id,
            'pipeline_id' => $pipelineA->id,
            'pipeline_stage_id' => $stageA->id,
        ]);

        $this->assertSame(0, CampaignEnrollment::count());
    }

    public function test_an_inactive_campaign_with_a_matching_trigger_event_does_not_enroll_anyone(): void
    {
        $location = Location::factory()->create();
        $contact = Contact::factory()->create(['location_id' => $location->id]);
        $pipeline = Pipeline::factory()->create(['location_id' => $location->id]);
        $stage = $pipeline->stages()->create(['name' => 'Hot Leads', 'position' => 0]);

        Campaign::factory()->create([
            'location_id' => $location->id,
            'trigger_event' => 'opportunity_stage:Hot Leads',
            'is_active' => false,
        ]);

        Opportunity::factory()->create([
            'location_id' => $location->id,
            'contact_id' => $contact->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
        ]);

        $this->assertSame(0, CampaignEnrollment::count());
    }

    public function test_create_and_edit_forms_render_a_trigger_event_dropdown_built_from_pipeline_stage_names(): void
    {
        $location = Location::factory()->create();
        $user = User::factory()->create(['current_location_id' => $location->id]);
        $user->locations()->attach($location->id, ['role' => 'owner']);
        $this->actingAs($user);

        $pipeline = Pipeline::factory()->create();
        $pipeline->stages()->create(['name' => 'New Leads', 'position' => 0]);

        $createResponse = $this->get('/campaigns/create');
        $createResponse->assertOk();
        $createResponse->assertSee('opportunity_stage:New Leads', false);

        $campaign = Campaign::factory()->create(['trigger_event' => 'opportunity_stage:New Leads']);

        $editResponse = $this->get("/campaigns/{$campaign->id}/edit");
        $editResponse->assertOk();
        $editResponse->assertSee('opportunity_stage:New Leads', false);
    }

    public function test_create_form_shows_an_empty_state_when_the_location_has_no_pipeline_stages_yet(): void
    {
        $location = Location::factory()->create();
        $user = User::factory()->create(['current_location_id' => $location->id]);
        $user->locations()->attach($location->id, ['role' => 'owner']);
        $this->actingAs($user);

        $response = $this->get('/campaigns/create');

        $response->assertOk();
        $response->assertSee('No pipeline stages exist yet');
    }
}
