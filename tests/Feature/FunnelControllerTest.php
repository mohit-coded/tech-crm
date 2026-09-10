<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunnelControllerTest extends TestCase
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

    public function test_index_only_lists_funnels_from_the_authenticated_users_location(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB] = $this->makeUserWithLocation();

        $this->actingAs($userA);
        Funnel::factory()->create(['name' => 'Alice Funnel', 'slug' => 'alice-funnel']);

        $this->actingAs($userB);
        Funnel::factory()->create(['name' => 'Bob Funnel', 'slug' => 'bob-funnel']);

        $this->actingAs($userA);
        $response = $this->get('/funnels');

        $response->assertOk();
        $response->assertSee('Alice Funnel');
        $response->assertDontSee('Bob Funnel');
    }

    public function test_store_creates_a_funnel_scoped_to_the_authenticated_users_location_via_the_trait(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $response = $this->post('/funnels', [
            'name' => 'Spring Promo',
            'slug' => 'spring-promo',
            'headline' => 'Get 20% Off',
            'subheadline' => 'Limited time only',
            'button_text' => 'Claim Offer',
            'is_published' => '1',
        ]);

        $response->assertRedirect(route('funnels.index'));

        $funnel = Funnel::sole();
        $this->assertSame($location->id, $funnel->location_id);
        $this->assertSame('Spring Promo', $funnel->name);
        $this->assertSame('spring-promo', $funnel->slug);
        $this->assertTrue($funnel->is_published);
    }

    public function test_cannot_view_edit_form_for_a_funnel_from_another_location(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB] = $this->makeUserWithLocation();

        $this->actingAs($userB);
        $funnelB = Funnel::factory()->create(['slug' => 'b-funnel']);

        $this->actingAs($userA);
        $response = $this->get("/funnels/{$funnelB->id}/edit");

        $response->assertNotFound();
    }

    public function test_cannot_update_a_funnel_from_another_location(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB] = $this->makeUserWithLocation();

        $this->actingAs($userB);
        $funnelB = Funnel::factory()->create(['name' => 'Bob Funnel', 'slug' => 'b-funnel']);

        $this->actingAs($userA);
        $response = $this->put("/funnels/{$funnelB->id}", [
            'name' => 'Hacked',
            'slug' => 'b-funnel',
            'headline' => 'Hacked',
            'button_text' => 'Claim Offer',
        ]);

        $response->assertNotFound();
        $this->assertSame('Bob Funnel', $funnelB->fresh()->name);
    }

    public function test_cannot_delete_a_funnel_from_another_location(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB] = $this->makeUserWithLocation();

        $this->actingAs($userB);
        $funnelB = Funnel::factory()->create(['slug' => 'b-funnel']);

        $this->actingAs($userA);
        $response = $this->delete("/funnels/{$funnelB->id}");

        $response->assertNotFound();
        $this->assertNotNull($funnelB->fresh());
    }
}
