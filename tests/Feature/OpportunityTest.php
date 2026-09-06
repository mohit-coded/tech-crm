<?php

namespace Tests\Feature;

use App\Events\OpportunityStageChanged;
use App\Models\Contact;
use App\Models\Location;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OpportunityTest extends TestCase
{
    use RefreshDatabase;

    public function test_opportunity_is_created_scoped_to_correct_location(): void
    {
        $locationA = Location::factory()->create();
        $locationB = Location::factory()->create();

        $userA = User::factory()->create(['current_location_id' => $locationA->id]);
        $userA->locations()->attach($locationA->id, ['role' => 'owner']);

        $userB = User::factory()->create(['current_location_id' => $locationB->id]);
        $userB->locations()->attach($locationB->id, ['role' => 'owner']);

        $this->actingAs($userA);

        $contact = Contact::factory()->create();
        $pipeline = Pipeline::factory()->create();
        $stage = $pipeline->stages()->create(['name' => 'New Leads', 'position' => 0]);

        $opportunity = Opportunity::factory()->create([
            'location_id' => null,
            'contact_id' => $contact->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
        ]);

        $this->assertSame($locationA->id, $opportunity->fresh()->location_id);

        // The global scope should hide it once we're acting as a user from a different location.
        $this->actingAs($userB);
        $this->assertSame(0, Opportunity::count());

        $this->actingAs($userA);
        $this->assertSame(1, Opportunity::count());
    }

    public function test_move_to_stage_changes_pipeline_stage_id_and_dispatches_event(): void
    {
        // Faking only this event (rather than Event::fake()) keeps Eloquent's
        // own creating/saving model events working, which BelongsToLocation
        // relies on to auto-fill location_id.
        Event::fake([OpportunityStageChanged::class]);

        $location = Location::factory()->create();
        $user = User::factory()->create(['current_location_id' => $location->id]);
        $user->locations()->attach($location->id, ['role' => 'owner']);

        $this->actingAs($user);

        $contact = Contact::factory()->create();
        $pipeline = Pipeline::factory()->create();
        $oldStage = $pipeline->stages()->create(['name' => 'New Leads', 'position' => 0]);
        $newStage = $pipeline->stages()->create(['name' => 'Hot Leads', 'position' => 1]);

        $opportunity = Opportunity::factory()->create([
            'location_id' => null,
            'contact_id' => $contact->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $oldStage->id,
        ]);

        $opportunity->moveToStage($newStage);

        $this->assertSame($newStage->id, $opportunity->fresh()->pipeline_stage_id);

        Event::assertDispatched(
            OpportunityStageChanged::class,
            fn (OpportunityStageChanged $event) => $event->opportunity->is($opportunity)
                && $event->oldStageId === $oldStage->id
                && $event->newStageId === $newStage->id
        );
    }
}
