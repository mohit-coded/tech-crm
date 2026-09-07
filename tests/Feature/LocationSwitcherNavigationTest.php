<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationSwitcherNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_nav_lists_all_locations_the_user_belongs_to(): void
    {
        $firstLocation = Location::factory()->create(['name' => 'Downtown Plumbing']);
        $secondLocation = Location::factory()->create(['name' => 'Uptown Plumbing']);

        $user = User::factory()->create([
            'current_location_id' => $firstLocation->id,
        ]);
        $user->locations()->attach($firstLocation->id, ['role' => 'owner']);
        $user->locations()->attach($secondLocation->id, ['role' => 'manager']);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertSee('Downtown Plumbing');
        $response->assertSee('Uptown Plumbing');
    }

    public function test_switching_location_updates_current_location_and_redirects_back(): void
    {
        $firstLocation = Location::factory()->create();
        $secondLocation = Location::factory()->create();

        $user = User::factory()->create([
            'current_location_id' => $firstLocation->id,
        ]);
        $user->locations()->attach($firstLocation->id, ['role' => 'owner']);
        $user->locations()->attach($secondLocation->id, ['role' => 'manager']);

        $response = $this->actingAs($user)
            ->from('/profile')
            ->post(route('locations.switch', $secondLocation));

        $response->assertRedirect('/profile');
        $this->assertSame($secondLocation->id, $user->fresh()->current_location_id);
    }
}
