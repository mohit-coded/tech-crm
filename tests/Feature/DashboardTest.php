<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Location;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_only_shows_data_for_the_authenticated_users_own_location(): void
    {
        $locationA = Location::factory()->create();
        $userA = User::factory()->create(['current_location_id' => $locationA->id]);
        $userA->locations()->attach($locationA->id, ['role' => 'owner']);

        $locationB = Location::factory()->create();
        $userB = User::factory()->create(['current_location_id' => $locationB->id]);
        $userB->locations()->attach($locationB->id, ['role' => 'owner']);

        // Location A: 3 open opportunities across two stages.
        $this->actingAs($userA);
        $contactA = Contact::factory()->create();
        $pipelineA = Pipeline::factory()->create();
        $stageA1 = $pipelineA->stages()->create(['name' => 'A Stage One', 'position' => 0]);
        $stageA2 = $pipelineA->stages()->create(['name' => 'A Stage Two', 'position' => 1]);

        Opportunity::factory()->create([
            'location_id' => null,
            'contact_id' => $contactA->id,
            'pipeline_id' => $pipelineA->id,
            'pipeline_stage_id' => $stageA1->id,
            'name' => 'Opportunity A1',
            'monetary_value' => 100,
        ]);
        Opportunity::factory()->create([
            'location_id' => null,
            'contact_id' => $contactA->id,
            'pipeline_id' => $pipelineA->id,
            'pipeline_stage_id' => $stageA1->id,
            'name' => 'Opportunity A2',
            'monetary_value' => 200,
        ]);
        Opportunity::factory()->create([
            'location_id' => null,
            'contact_id' => $contactA->id,
            'pipeline_id' => $pipelineA->id,
            'pipeline_stage_id' => $stageA2->id,
            'name' => 'Opportunity A3',
            'monetary_value' => 50,
        ]);

        // Location B: 2 open opportunities of its own, with a much larger
        // total value — if scoping ever leaked, this would dominate the sum.
        $this->actingAs($userB);
        $contactB = Contact::factory()->create();
        $pipelineB = Pipeline::factory()->create();
        $stageB1 = $pipelineB->stages()->create(['name' => 'B Only Stage', 'position' => 0]);

        Opportunity::factory()->create([
            'location_id' => null,
            'contact_id' => $contactB->id,
            'pipeline_id' => $pipelineB->id,
            'pipeline_stage_id' => $stageB1->id,
            'name' => 'Opportunity B1',
            'monetary_value' => 9000,
        ]);
        Opportunity::factory()->create([
            'location_id' => null,
            'contact_id' => $contactB->id,
            'pipeline_id' => $pipelineB->id,
            'pipeline_stage_id' => $stageB1->id,
            'name' => 'Opportunity B2',
            'monetary_value' => 1000,
        ]);

        $this->actingAs($userA);
        $response = $this->get('/dashboard');

        $response->assertOk();

        $response->assertViewHas('openCount', 3);
        $response->assertViewHas('pipelineValue', fn ($value) => (float) $value === 350.0);

        $response->assertViewHas(
            'stageBreakdown',
            fn ($stages) => $stages->pluck('stage_name')->sort()->values()->all()
                === ['A Stage One', 'A Stage Two']
        );

        $response->assertViewHas(
            'recentOpportunities',
            fn ($opportunities) => $opportunities->pluck('name')->sort()->values()->all()
                === ['Opportunity A1', 'Opportunity A2', 'Opportunity A3']
        );

        $response->assertSee('3');
        $response->assertSee('$350.00');
        $response->assertSee('Opportunity A1');
        $response->assertSee('A Stage One');
        $response->assertSee('A Stage Two');

        $response->assertDontSee('Opportunity B1');
        $response->assertDontSee('Opportunity B2');
        $response->assertDontSee('B Only Stage');
        $response->assertDontSee('9000');
        $response->assertDontSee('$10,350.00');
    }
}
