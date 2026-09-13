<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignStep;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Location}
     */
    private function makeUserWithLocation(): array
    {
        $location = Location::factory()->create();
        $user = User::factory()->create(['current_location_id' => $location->id]);
        $user->locations()->attach($location->id, ['role' => 'owner']);

        return [$user, $location];
    }

    public function test_index_only_lists_campaigns_from_the_authenticated_users_location(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB] = $this->makeUserWithLocation();

        $this->actingAs($userA);
        Campaign::factory()->create(['name' => 'Alice Campaign']);

        $this->actingAs($userB);
        Campaign::factory()->create(['name' => 'Bob Campaign']);

        $this->actingAs($userA);
        $response = $this->get('/campaigns');

        $response->assertOk();
        $response->assertSee('Alice Campaign');
        $response->assertDontSee('Bob Campaign');
    }

    public function test_store_creates_a_campaign_scoped_to_the_authenticated_users_location_via_the_trait(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $response = $this->post('/campaigns', [
            'name' => 'Post-Appointment Follow-up',
            'trigger_event' => 'appointment_confirmed',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('campaigns.index'));

        $campaign = Campaign::sole();
        $this->assertSame($location->id, $campaign->location_id);
        $this->assertSame('Post-Appointment Follow-up', $campaign->name);
        $this->assertSame('appointment_confirmed', $campaign->trigger_event);
        $this->assertTrue($campaign->is_active);
    }

    public function test_edit_page_shows_existing_steps(): void
    {
        [$user] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $campaign = Campaign::factory()->create(['name' => 'Follow-up']);
        CampaignStep::factory()->create([
            'campaign_id' => $campaign->id,
            'position' => 0,
            'channel' => 'sms',
            'body' => 'Thanks for booking!',
        ]);

        $response = $this->get("/campaigns/{$campaign->id}/edit");

        $response->assertOk();
        $response->assertSee('Thanks for booking!');
    }

    public function test_update_can_add_a_new_step(): void
    {
        [$user] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $campaign = Campaign::factory()->create();

        $response = $this->put("/campaigns/{$campaign->id}", [
            'name' => $campaign->name,
            'trigger_event' => $campaign->trigger_event,
            'new_steps' => [
                ['channel' => 'sms', 'body' => 'Welcome aboard!', 'delay_minutes' => '10'],
                ['channel' => '', 'body' => '', 'delay_minutes' => ''],
            ],
        ]);

        $response->assertRedirect(route('campaigns.edit', $campaign));

        $step = CampaignStep::where('campaign_id', $campaign->id)->sole();
        $this->assertSame('sms', $step->channel);
        $this->assertSame('Welcome aboard!', $step->body);
        $this->assertSame(10, $step->delay_minutes);
        $this->assertSame(0, $step->position);
    }

    public function test_update_can_remove_a_step(): void
    {
        [$user] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $campaign = Campaign::factory()->create();
        $step = CampaignStep::factory()->create(['campaign_id' => $campaign->id]);

        $response = $this->put("/campaigns/{$campaign->id}", [
            'name' => $campaign->name,
            'trigger_event' => $campaign->trigger_event,
            'steps' => [
                $step->id => [
                    'channel' => $step->channel,
                    'body' => $step->body,
                    'delay_minutes' => $step->delay_minutes,
                    'remove' => '1',
                ],
            ],
        ]);

        $response->assertRedirect(route('campaigns.edit', $campaign));
        $this->assertNull(CampaignStep::find($step->id));
    }

    public function test_update_can_edit_an_existing_step(): void
    {
        [$user] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $campaign = Campaign::factory()->create();
        $step = CampaignStep::factory()->create([
            'campaign_id' => $campaign->id,
            'channel' => 'sms',
            'body' => 'Original body',
            'delay_minutes' => 0,
        ]);

        $response = $this->put("/campaigns/{$campaign->id}", [
            'name' => $campaign->name,
            'trigger_event' => $campaign->trigger_event,
            'steps' => [
                $step->id => [
                    'channel' => 'email',
                    'body' => 'Updated body',
                    'delay_minutes' => '60',
                ],
            ],
        ]);

        $response->assertRedirect(route('campaigns.edit', $campaign));

        $step->refresh();
        $this->assertSame('email', $step->channel);
        $this->assertSame('Updated body', $step->body);
        $this->assertSame(60, $step->delay_minutes);
    }

    public function test_cannot_view_edit_form_for_a_campaign_from_another_location(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB] = $this->makeUserWithLocation();

        $this->actingAs($userB);
        $campaignB = Campaign::factory()->create();

        $this->actingAs($userA);
        $response = $this->get("/campaigns/{$campaignB->id}/edit");

        $response->assertNotFound();
    }

    public function test_cannot_update_a_campaign_from_another_location(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB] = $this->makeUserWithLocation();

        $this->actingAs($userB);
        $campaignB = Campaign::factory()->create(['name' => 'Bob Campaign']);

        $this->actingAs($userA);
        $response = $this->put("/campaigns/{$campaignB->id}", [
            'name' => 'Hacked',
            'trigger_event' => 'hacked_event',
        ]);

        $response->assertNotFound();
        $this->assertSame('Bob Campaign', $campaignB->fresh()->name);
    }

    public function test_cannot_delete_a_campaign_from_another_location(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB] = $this->makeUserWithLocation();

        $this->actingAs($userB);
        $campaignB = Campaign::factory()->create();

        $this->actingAs($userA);
        $response = $this->delete("/campaigns/{$campaignB->id}");

        $response->assertNotFound();
        $this->assertNotNull($campaignB->fresh());
    }

    // The important one: a foreign step id smuggled into your own
    // campaign's update must be silently ignored, not modified or
    // deleted — same adversarial pattern as
    // CalendarControllerTest::test_cannot_modify_another_locations_availability_rule_by_id_through_own_calendar.
    public function test_cannot_modify_another_locations_step_by_id_through_own_campaign(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB] = $this->makeUserWithLocation();

        $this->actingAs($userB);
        $campaignB = Campaign::factory()->create();
        $stepB = CampaignStep::factory()->create([
            'campaign_id' => $campaignB->id,
            'channel' => 'sms',
            'body' => 'Original B body',
            'delay_minutes' => 5,
        ]);

        $this->actingAs($userA);
        $campaignA = Campaign::factory()->create();

        // Attempt to smuggle userB's step id into userA's own (legitimate)
        // campaign update — it doesn't belong to campaignA's steps()
        // relation, so it must be silently ignored, not updated or
        // deleted.
        $response = $this->put("/campaigns/{$campaignA->id}", [
            'name' => $campaignA->name,
            'trigger_event' => $campaignA->trigger_event,
            'steps' => [
                $stepB->id => [
                    'channel' => 'email',
                    'body' => 'Hacked body',
                    'delay_minutes' => '999',
                    'remove' => '',
                ],
            ],
        ]);

        $response->assertRedirect(route('campaigns.edit', $campaignA));

        $stepB->refresh();
        $this->assertSame('sms', $stepB->channel);
        $this->assertSame('Original B body', $stepB->body);
        $this->assertSame(5, $stepB->delay_minutes);
        $this->assertSame($campaignB->id, $stepB->campaign_id);
    }

    public function test_cannot_remove_another_locations_step_by_id_through_own_campaign(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB] = $this->makeUserWithLocation();

        $this->actingAs($userB);
        $campaignB = Campaign::factory()->create();
        $stepB = CampaignStep::factory()->create(['campaign_id' => $campaignB->id]);

        $this->actingAs($userA);
        $campaignA = Campaign::factory()->create();

        $response = $this->put("/campaigns/{$campaignA->id}", [
            'name' => $campaignA->name,
            'trigger_event' => $campaignA->trigger_event,
            'steps' => [
                $stepB->id => [
                    'channel' => $stepB->channel,
                    'body' => $stepB->body,
                    'delay_minutes' => $stepB->delay_minutes,
                    'remove' => '1',
                ],
            ],
        ]);

        $response->assertRedirect(route('campaigns.edit', $campaignA));

        // Still exists — the smuggled removal never touched it.
        $this->assertNotNull(CampaignStep::find($stepB->id));
        $this->assertSame($campaignB->id, $stepB->fresh()->campaign_id);
    }
}
