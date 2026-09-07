<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactControllerTest extends TestCase
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

    public function test_index_only_lists_contacts_from_the_authenticated_users_location(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB] = $this->makeUserWithLocation();

        $this->actingAs($userA);
        Contact::factory()->create(['first_name' => 'Alice']);

        $this->actingAs($userB);
        Contact::factory()->create(['first_name' => 'Bob']);

        $this->actingAs($userA);
        $response = $this->get('/contacts');

        $response->assertOk();
        $response->assertSee('Alice');
        $response->assertDontSee('Bob');
    }

    public function test_store_creates_a_contact_scoped_to_the_authenticated_users_location_via_the_trait(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $response = $this->post('/contacts', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '555-1234',
        ]);

        $response->assertRedirect(route('contacts.index'));

        $contact = Contact::sole();
        $this->assertSame($location->id, $contact->location_id);
        $this->assertSame('Jane', $contact->first_name);
    }

    public function test_cannot_view_edit_form_for_a_contact_from_another_location(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB] = $this->makeUserWithLocation();

        $this->actingAs($userB);
        $contactB = Contact::factory()->create();

        $this->actingAs($userA);
        $response = $this->get("/contacts/{$contactB->id}/edit");

        $response->assertNotFound();
    }

    public function test_cannot_update_a_contact_from_another_location(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB] = $this->makeUserWithLocation();

        $this->actingAs($userB);
        $contactB = Contact::factory()->create(['first_name' => 'Bob']);

        $this->actingAs($userA);
        $response = $this->put("/contacts/{$contactB->id}", [
            'first_name' => 'Hacked',
        ]);

        $response->assertNotFound();
        $this->assertSame('Bob', $contactB->fresh()->first_name);
    }

    public function test_cannot_delete_a_contact_from_another_location(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB] = $this->makeUserWithLocation();

        $this->actingAs($userB);
        $contactB = Contact::factory()->create();

        $this->actingAs($userA);
        $response = $this->delete("/contacts/{$contactB->id}");

        $response->assertNotFound();
        $this->assertNotNull($contactB->fresh());
    }
}
