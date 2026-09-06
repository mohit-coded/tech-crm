<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Location;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpportunityStageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_move_opportunity_to_stage_from_another_location(): void
    {
        $locationA = Location::factory()->create();
        $locationB = Location::factory()->create();

        $userA = User::factory()->create(['current_location_id' => $locationA->id]);
        $userA->locations()->attach($locationA->id, ['role' => 'owner']);

        $this->actingAs($userA);

        $contact = Contact::factory()->create();
        $pipelineA = Pipeline::factory()->create();
        $stageA = $pipelineA->stages()->create(['name' => 'New Leads', 'position' => 0]);

        $opportunity = Opportunity::factory()->create([
            'location_id' => null,
            'contact_id' => $contact->id,
            'pipeline_id' => $pipelineA->id,
            'pipeline_stage_id' => $stageA->id,
        ]);

        // Created explicitly in locationB, bypassing the current actingAs
        // user's tenant, so it's a genuine cross-tenant stage.
        $pipelineB = Pipeline::factory()->create(['location_id' => $locationB->id]);
        $stageB = $pipelineB->stages()->create(['name' => 'Other Stage', 'position' => 0]);

        $response = $this->patchJson("/api/opportunities/{$opportunity->id}/stage", [
            'pipeline_stage_id' => $stageB->id,
        ]);

        $response->assertForbidden();
        $this->assertSame($stageA->id, $opportunity->fresh()->pipeline_stage_id);
    }

    public function test_can_move_opportunity_to_stage_in_same_location(): void
    {
        $location = Location::factory()->create();

        $user = User::factory()->create(['current_location_id' => $location->id]);
        $user->locations()->attach($location->id, ['role' => 'owner']);

        $this->actingAs($user);

        $contact = Contact::factory()->create();
        $pipeline = Pipeline::factory()->create();
        $stageOne = $pipeline->stages()->create(['name' => 'New Leads', 'position' => 0]);
        $stageTwo = $pipeline->stages()->create(['name' => 'Hot Leads', 'position' => 1]);

        $opportunity = Opportunity::factory()->create([
            'location_id' => null,
            'contact_id' => $contact->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stageOne->id,
        ]);

        $response = $this->patchJson("/api/opportunities/{$opportunity->id}/stage", [
            'pipeline_stage_id' => $stageTwo->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('pipeline_stage_id', $stageTwo->id);
        $this->assertSame($stageTwo->id, $opportunity->fresh()->pipeline_stage_id);
    }
}
