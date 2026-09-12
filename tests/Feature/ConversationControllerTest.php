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

class ConversationControllerTest extends TestCase
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

    public function test_index_only_lists_contacts_with_messages_from_the_authenticated_users_location(): void
    {
        [$userA, $locationA] = $this->makeUserWithLocation();
        [$userB, $locationB] = $this->makeUserWithLocation();

        $this->actingAs($userA);
        $contactWithMessage = Contact::factory()->create(['location_id' => $locationA->id, 'first_name' => 'Alice']);
        Message::factory()->create([
            'location_id' => $locationA->id,
            'contact_id' => $contactWithMessage->id,
            'body' => 'Hi Alice, see you Friday!',
        ]);

        // A contact with no messages at all must not show up.
        Contact::factory()->create(['location_id' => $locationA->id, 'first_name' => 'NoMessagesYet']);

        $this->actingAs($userB);
        $contactB = Contact::factory()->create(['location_id' => $locationB->id, 'first_name' => 'Bob']);
        Message::factory()->create([
            'location_id' => $locationB->id,
            'contact_id' => $contactB->id,
            'body' => 'Hi Bob',
        ]);

        $this->actingAs($userA);
        $response = $this->get('/conversations');

        $response->assertOk();
        $response->assertSee('Alice');
        $response->assertSee('Hi Alice, see you Friday!');
        $response->assertDontSee('Bob');
        $response->assertDontSee('NoMessagesYet');
    }

    public function test_index_orders_contacts_by_their_most_recent_message_descending(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $olderContact = Contact::factory()->create(['location_id' => $location->id, 'first_name' => 'Older']);
        Message::factory()->create([
            'location_id' => $location->id,
            'contact_id' => $olderContact->id,
            'body' => 'First conversation',
            'created_at' => now()->subDays(2),
        ]);

        $newerContact = Contact::factory()->create(['location_id' => $location->id, 'first_name' => 'Newer']);
        Message::factory()->create([
            'location_id' => $location->id,
            'contact_id' => $newerContact->id,
            'body' => 'Most recent conversation',
            'created_at' => now()->subHour(),
        ]);

        $response = $this->get('/conversations');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, 'Older'),
            strpos($content, 'Newer'),
            'Expected the contact with the more recent message to be listed first.'
        );
    }

    public function test_show_404s_for_a_contact_from_another_location(): void
    {
        [$userA] = $this->makeUserWithLocation();
        [$userB, $locationB] = $this->makeUserWithLocation();

        $this->actingAs($userB);
        $contactB = Contact::factory()->create(['location_id' => $locationB->id]);
        Message::factory()->create(['location_id' => $locationB->id, 'contact_id' => $contactB->id]);

        $this->actingAs($userA);
        $response = $this->get("/conversations/{$contactB->id}");

        $response->assertNotFound();
    }

    public function test_show_displays_inbound_and_outbound_messages_in_chronological_order(): void
    {
        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $contact = Contact::factory()->create(['location_id' => $location->id]);

        $first = Message::factory()->create([
            'location_id' => $location->id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'body' => 'Hi! Just confirming your appointment.',
            'created_at' => now()->subMinutes(10),
        ]);
        $second = Message::factory()->create([
            'location_id' => $location->id,
            'contact_id' => $contact->id,
            'direction' => 'inbound',
            'body' => 'Sounds good, see you then.',
            'created_at' => now()->subMinutes(5),
        ]);
        $third = Message::factory()->create([
            'location_id' => $location->id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'body' => 'Great, see you soon!',
            'created_at' => now(),
        ]);

        $response = $this->get("/conversations/{$contact->id}");

        $response->assertOk();
        $content = $response->getContent();

        $firstPos = strpos($content, $first->body);
        $secondPos = strpos($content, $second->body);
        $thirdPos = strpos($content, $third->body);

        $this->assertNotFalse($firstPos);
        $this->assertNotFalse($secondPos);
        $this->assertNotFalse($thirdPos);
        $this->assertLessThan($secondPos, $firstPos);
        $this->assertLessThan($thirdPos, $secondPos);
    }

    public function test_sending_a_message_from_the_thread_redirects_back_to_the_same_thread_and_appears_in_it(): void
    {
        Queue::fake();

        [$user, $location] = $this->makeUserWithLocation();
        $this->actingAs($user);

        $contact = Contact::factory()->create(['location_id' => $location->id]);
        $threadUrl = "/conversations/{$contact->id}";

        $response = $this->from($threadUrl)->post('/messages', [
            'contact_id' => $contact->id,
            'body' => 'Hey, are we still on for tomorrow?',
        ]);

        $response->assertRedirect($threadUrl);

        $message = Message::withoutGlobalScopes()->sole();
        $this->assertSame($contact->id, $message->contact_id);
        $this->assertSame('Hey, are we still on for tomorrow?', $message->body);

        Queue::assertPushed(SendSmsMessage::class);

        $thread = $this->get($threadUrl);
        $thread->assertOk();
        $thread->assertSee('Hey, are we still on for tomorrow?');
    }
}
