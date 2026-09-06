<?php

namespace Tests\Feature\Auth;

use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'business_name' => 'Acme Plumbing',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registering_with_business_name_creates_location_and_owner_membership(): void
    {
        $response = $this->post('/register', [
            'business_name' => 'Acme Plumbing',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('locations', 1);
        $this->assertDatabaseCount('location_user', 1);

        $user = User::sole();
        $location = Location::sole();

        $this->assertSame('Acme Plumbing', $location->name);
        $this->assertSame($location->id, $user->current_location_id);

        $this->assertDatabaseHas('location_user', [
            'user_id' => $user->id,
            'location_id' => $location->id,
            'role' => 'owner',
        ]);
    }

    public function test_no_user_is_left_behind_if_location_creation_fails(): void
    {
        // Force the Location insert to fail after the User has already
        // been created, to prove the transaction rolls both back.
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        $response = $this->post('/register', [
            'business_name' => 'Acme Plumbing',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(500);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }
}
