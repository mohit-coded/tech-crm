<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_visiting_root_is_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_visiting_root_is_redirected_to_dashboard(): void
    {
        $location = Location::factory()->create();
        $user = User::factory()->create(['current_location_id' => $location->id]);

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect(route('dashboard'));
    }
}
