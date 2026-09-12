<?php

namespace Tests\Feature;

use App\Jobs\SendSmsMessage;
use App\Models\Contact;
use App\Models\Location;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MessageControllerTest extends TestCase
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

    public function test_sending_creates_a_queued_message_and_dispatches_the_job(): void
    {
        Queue::fake();

        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $contact = Contact::factory()->create(['location_id' => $location->id]);

        $response = $this->post('/messages', [
            'contact_id' => $contact->id,
            'body' => 'Hey, just confirming your appointment!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $message = Message::withoutGlobalScopes()->sole();
        $this->assertSame($location->id, $message->location_id);
        $this->assertSame($contact->id, $message->contact_id);
        $this->assertSame('outbound', $message->direction);
        $this->assertSame('queued', $message->status);
        $this->assertSame('Hey, just confirming your appointment!', $message->body);

        Queue::assertPushed(SendSmsMessage::class, function (SendSmsMessage $job) use ($message) {
            return $job->message->is($message);
        });
    }

    // Tenant isolation: a user cannot send a message to another
    // location's contact — same pattern as everywhere else.
    public function test_cannot_send_a_message_to_another_locations_contact(): void
    {
        Queue::fake();

        [$userA] = $this->makeUserWithLocation();
        [$userB, $locationB] = $this->makeUserWithLocation();

        $this->actingAs($userB);
        $contactB = Contact::factory()->create(['location_id' => $locationB->id]);

        $this->actingAs($userA);
        $response = $this->post('/messages', [
            'contact_id' => $contactB->id,
            'body' => 'Attempted cross-tenant message',
        ]);

        $response->assertNotFound();

        $this->assertSame(0, Message::withoutGlobalScopes()->count());
        Queue::assertNotPushed(SendSmsMessage::class);
    }
}
