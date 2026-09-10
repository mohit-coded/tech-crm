<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Location;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpportunityBoardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_only_shows_the_authenticated_users_own_location_pipeline(): void
    {
        $locationA = Location::factory()->create();
        $userA = User::factory()->create(['current_location_id' => $locationA->id]);
        $userA->locations()->attach($locationA->id, ['role' => 'owner']);

        $locationB = Location::factory()->create();
        $userB = User::factory()->create(['current_location_id' => $locationB->id]);
        $userB->locations()->attach($locationB->id, ['role' => 'owner']);

        // Location A: default pipeline with two stages and one open opportunity each.
        $this->actingAs($userA);
        $contactA = Contact::factory()->create(['first_name' => 'Alice', 'last_name' => 'Anderson']);
        $pipelineA = Pipeline::factory()->create(['name' => 'A Pipeline', 'is_default' => true]);
        $stageA1 = $pipelineA->stages()->create(['name' => 'A Stage One', 'position' => 0]);
        $stageA2 = $pipelineA->stages()->create(['name' => 'A Stage Two', 'position' => 1]);

        Opportunity::factory()->create([
            'location_id' => null,
            'contact_id' => $contactA->id,
            'pipeline_id' => $pipelineA->id,
            'pipeline_stage_id' => $stageA1->id,
            'name' => 'Opportunity A1',
            'monetary_value' => 100,
            'status' => 'open',
        ]);

        // Location B: its own pipeline/stage/opportunity — must never leak into A's board.
        $this->actingAs($userB);
        $contactB = Contact::factory()->create(['first_name' => 'Bob', 'last_name' => 'Brown']);
        $pipelineB = Pipeline::factory()->create(['name' => 'B Pipeline', 'is_default' => true]);
        $stageB1 = $pipelineB->stages()->create(['name' => 'B Only Stage', 'position' => 0]);

        Opportunity::factory()->create([
            'location_id' => null,
            'contact_id' => $contactB->id,
            'pipeline_id' => $pipelineB->id,
            'pipeline_stage_id' => $stageB1->id,
            'name' => 'Opportunity B1',
            'monetary_value' => 9000,
            'status' => 'open',
        ]);

        $this->actingAs($userA);
        $response = $this->get('/opportunities');

        $response->assertOk();

        $response->assertViewHas(
            'pipeline',
            fn ($pipeline) => $pipeline->id === $pipelineA->id
        );

        $response->assertViewHas(
            'stages',
            fn ($stages) => $stages->pluck('name')->sort()->values()->all()
                === ['A Stage One', 'A Stage Two']
        );

        $response->assertSee('A Stage One');
        $response->assertSee('A Stage Two');
        $response->assertSee('Opportunity A1');
        $response->assertSee('Alice Anderson');
        $response->assertSee('$100.00');

        $response->assertDontSee('B Only Stage');
        $response->assertDontSee('Opportunity B1');
        $response->assertDontSee('Bob Brown');
        $response->assertDontSee('9000');
        $response->assertDontSee('B Pipeline');
    }

    public function test_board_excludes_non_open_opportunities(): void
    {
        $location = Location::factory()->create();
        $user = User::factory()->create(['current_location_id' => $location->id]);
        $user->locations()->attach($location->id, ['role' => 'owner']);

        $this->actingAs($user);

        $contact = Contact::factory()->create();
        $pipeline = Pipeline::factory()->create(['is_default' => true]);
        $stage = $pipeline->stages()->create(['name' => 'New Leads', 'position' => 0]);

        Opportunity::factory()->create([
            'location_id' => null,
            'contact_id' => $contact->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
            'name' => 'Won Opportunity',
            'status' => 'won',
        ]);

        $response = $this->get('/opportunities');

        $response->assertOk();
        $response->assertDontSee('Won Opportunity');
    }

    public function test_board_falls_back_to_first_pipeline_when_none_is_default(): void
    {
        $location = Location::factory()->create();
        $user = User::factory()->create(['current_location_id' => $location->id]);
        $user->locations()->attach($location->id, ['role' => 'owner']);

        $this->actingAs($user);

        $pipeline = Pipeline::factory()->create(['name' => 'Only Pipeline', 'is_default' => false]);
        $pipeline->stages()->create(['name' => 'Only Stage', 'position' => 0]);

        $response = $this->get('/opportunities');

        $response->assertOk();
        $response->assertViewHas('pipeline', fn ($p) => $p->id === $pipeline->id);
        $response->assertSee('Only Stage');
    }
}
