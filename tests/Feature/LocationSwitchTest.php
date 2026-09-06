<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_switch_to_location_they_do_not_belong_to(): void
    {
        $ownLocation = Location::factory()->create();
        $otherLocation = Location::factory()->create();

        $user = User::factory()->create([
            'current_location_id' => $ownLocation->id,
        ]);
        $user->locations()->attach($ownLocation->id, ['role' => 'owner']);

        $response = $this->actingAs($user)->post("/locations/switch/{$otherLocation->id}");

        $response->assertForbidden();
        $this->assertSame($ownLocation->id, $user->fresh()->current_location_id);
    }

    public function test_user_can_switch_to_location_they_belong_to(): void
    {
        $firstLocation = Location::factory()->create();
        $secondLocation = Location::factory()->create();

        $user = User::factory()->create([
            'current_location_id' => $firstLocation->id,
        ]);
        $user->locations()->attach($firstLocation->id, ['role' => 'owner']);
        $user->locations()->attach($secondLocation->id, ['role' => 'manager']);

        $response = $this->actingAs($user)->post("/locations/switch/{$secondLocation->id}");

        $response->assertRedirect();
        $this->assertSame($secondLocation->id, $user->fresh()->current_location_id);
    }
}
