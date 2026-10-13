<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Location;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpportunityStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUserInNewLocation(): User
    {
        $location = Location::factory()->create();
        $user = User::factory()->create(['current_location_id' => $location->id]);
        $user->locations()->attach($location->id, ['role' => 'owner']);

        $this->actingAs($user);

        return $user;
    }

    private function makeOpenOpportunity(): Opportunity
    {
        $contact = Contact::factory()->create();
        $pipeline = Pipeline::factory()->create();
        $stage = $pipeline->stages()->create(['name' => 'Hot Leads', 'position' => 0]);

        return Opportunity::factory()->create([
            'location_id' => null,
            'contact_id' => $contact->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
            'status' => 'open',
        ]);
    }

    public function test_marking_won_updates_status_without_changing_the_stage(): void
    {
        $this->actingAsUserInNewLocation();
        $opportunity = $this->makeOpenOpportunity();
        $stageId = $opportunity->pipeline_stage_id;

        $response = $this->patchJson("/api/opportunities/{$opportunity->id}/status", ['status' => 'won']);

        $response->assertOk();
        $response->assertJsonPath('status', 'won');
        $this->assertSame('won', $opportunity->fresh()->status);
        $this->assertSame($stageId, $opportunity->fresh()->pipeline_stage_id);
    }

    public function test_marking_lost_updates_status_without_changing_the_stage(): void
    {
        $this->actingAsUserInNewLocation();
        $opportunity = $this->makeOpenOpportunity();
        $stageId = $opportunity->pipeline_stage_id;

        $this->patchJson("/api/opportunities/{$opportunity->id}/status", ['status' => 'lost'])
            ->assertOk()
            ->assertJsonPath('status', 'lost');

        $this->assertSame('lost', $opportunity->fresh()->status);
        $this->assertSame($stageId, $opportunity->fresh()->pipeline_stage_id);
    }

    public function test_cannot_change_the_status_of_another_locations_opportunity(): void
    {
        // Owner of the opportunity: location A.
        $this->actingAsUserInNewLocation();
        $opportunity = $this->makeOpenOpportunity();

        // Acting user: location B.
        $this->actingAsUserInNewLocation();

        $this->patchJson("/api/opportunities/{$opportunity->id}/status", ['status' => 'won'])
            ->assertNotFound();

        $fresh = Opportunity::withoutGlobalScopes()->find($opportunity->id);
        $this->assertSame('open', $fresh->status);
        $this->assertSame($opportunity->pipeline_stage_id, $fresh->pipeline_stage_id);
    }

    public function test_an_invalid_status_value_is_rejected(): void
    {
        $this->actingAsUserInNewLocation();
        $opportunity = $this->makeOpenOpportunity();

        foreach (['open', 'WON', 'closed', ''] as $invalid) {
            $this->patchJson("/api/opportunities/{$opportunity->id}/status", ['status' => $invalid])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('status');
        }

        $this->patchJson("/api/opportunities/{$opportunity->id}/status", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame('open', $opportunity->fresh()->status);
    }

    public function test_marking_an_opportunity_won_is_counted_by_the_dashboard(): void
    {
        $this->actingAsUserInNewLocation();
        $won = $this->makeOpenOpportunity();
        $this->makeOpenOpportunity();

        $this->get('/dashboard')->assertViewHas('wonInRange', 0);

        $this->patchJson("/api/opportunities/{$won->id}/status", ['status' => 'won'])->assertOk();

        $this->get('/dashboard')
            ->assertViewHas('wonInRange', 1)
            ->assertViewHas('totalInRange', 2)
            ->assertViewHas('conversionRate', 50.0);
    }

    public function test_board_shows_won_lost_buttons_and_drops_a_closed_card_on_reload(): void
    {
        $this->actingAsUserInNewLocation();
        $opportunity = $this->makeOpenOpportunity();

        $this->get('/opportunities')
            ->assertOk()
            ->assertSee("setStatus('won')", false)
            ->assertSee("setStatus('lost')", false)
            ->assertSee('data-opportunity-id="'.$opportunity->id.'"', false);

        $this->patchJson("/api/opportunities/{$opportunity->id}/status", ['status' => 'won'])->assertOk();

        $this->get('/opportunities')
            ->assertOk()
            ->assertDontSee('data-opportunity-id="'.$opportunity->id.'"', false);
    }
}
