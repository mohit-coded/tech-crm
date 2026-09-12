<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Location;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Twilio\Security\RequestValidator;

class TwilioWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const AUTH_TOKEN = 'test-auth-token';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.twilio.token' => self::AUTH_TOKEN]);
    }

    /**
     * Computes a real signature via Twilio's own RequestValidator against
     * the fake auth token bound above, rather than hardcoding one.
     */
    private function signFor(string $url, array $params): string
    {
        return (new RequestValidator(self::AUTH_TOKEN))->computeSignature($url, $params);
    }

    private function webhookUrl(): string
    {
        return url('/webhooks/twilio/sms');
    }

    public function test_valid_signature_with_matching_to_number_creates_an_inbound_message_and_contact(): void
    {
        $location = Location::factory()->create(['twilio_phone_number' => '+15551234567']);

        $params = [
            'To' => '+15551234567',
            'From' => '+15559876543',
            'Body' => 'Hello, is this still open?',
            'MessageSid' => 'SM00000000000000000000000000001',
        ];

        $response = $this->withHeaders(['X-Twilio-Signature' => $this->signFor($this->webhookUrl(), $params)])
            ->post('/webhooks/twilio/sms', $params);

        $response->assertOk();
        $this->assertStringContainsString('<Response></Response>', $response->getContent());

        $message = Message::withoutGlobalScopes()->sole();
        $this->assertSame($location->id, $message->location_id);
        $this->assertSame('inbound', $message->direction);
        $this->assertSame('received', $message->status);
        $this->assertSame('Hello, is this still open?', $message->body);
        $this->assertSame('SM00000000000000000000000000001', $message->twilio_sid);

        $contact = Contact::withoutGlobalScopes()->sole();
        $this->assertSame($location->id, $contact->location_id);
        $this->assertSame('+15559876543', $contact->phone);
        $this->assertSame($contact->id, $message->contact_id);
    }

    public function test_a_second_inbound_message_from_the_same_number_reuses_the_existing_contact(): void
    {
        $location = Location::factory()->create(['twilio_phone_number' => '+15551234567']);
        $existingContact = Contact::factory()->create([
            'location_id' => $location->id,
            'phone' => '+15559876543',
            'first_name' => 'Jane Doe',
        ]);

        $params = [
            'To' => '+15551234567',
            'From' => '+15559876543',
            'Body' => 'Following up on my last message',
            'MessageSid' => 'SM00000000000000000000000000006',
        ];

        $response = $this->withHeaders(['X-Twilio-Signature' => $this->signFor($this->webhookUrl(), $params)])
            ->post('/webhooks/twilio/sms', $params);

        $response->assertOk();

        $message = Message::withoutGlobalScopes()->sole();
        $this->assertSame($existingContact->id, $message->contact_id);
        $this->assertSame(1, Contact::withoutGlobalScopes()->count());
    }

    // The most important test here: an invalid (or missing) signature
    // must be rejected before any other logic runs — no Message or
    // Contact created.
    public function test_invalid_signature_is_rejected_and_creates_nothing(): void
    {
        $location = Location::factory()->create(['twilio_phone_number' => '+15551234567']);

        $params = [
            'To' => '+15551234567',
            'From' => '+15559876543',
            'Body' => 'Hello?',
            'MessageSid' => 'SM00000000000000000000000000002',
        ];

        $response = $this->withHeaders(['X-Twilio-Signature' => 'totally-bogus-signature'])
            ->post('/webhooks/twilio/sms', $params);

        $response->assertForbidden();
        $this->assertSame(0, Message::withoutGlobalScopes()->where('location_id', $location->id)->count());
        $this->assertSame(0, Contact::withoutGlobalScopes()->where('phone', '+15559876543')->count());
    }

    public function test_missing_signature_is_rejected_and_creates_nothing(): void
    {
        Location::factory()->create(['twilio_phone_number' => '+15551234567']);

        $response = $this->post('/webhooks/twilio/sms', [
            'To' => '+15551234567',
            'From' => '+15559876543',
            'Body' => 'Hello?',
            'MessageSid' => 'SM00000000000000000000000000003',
        ]);

        $response->assertForbidden();
        $this->assertSame(0, Message::withoutGlobalScopes()->count());
    }

    public function test_valid_signature_with_unrecognized_to_number_does_nothing_and_returns_200(): void
    {
        // A location exists, but none has the 'To' number below.
        Location::factory()->create(['twilio_phone_number' => '+15551234567']);

        $params = [
            'To' => '+19999999999',
            'From' => '+15559876543',
            'Body' => 'Hello?',
            'MessageSid' => 'SM00000000000000000000000000004',
        ];

        $response = $this->withHeaders(['X-Twilio-Signature' => $this->signFor($this->webhookUrl(), $params)])
            ->post('/webhooks/twilio/sms', $params);

        $response->assertOk();
        $this->assertStringContainsString('<Response></Response>', $response->getContent());
        $this->assertSame(0, Message::withoutGlobalScopes()->count());
        $this->assertSame(0, Contact::withoutGlobalScopes()->count());
    }

    // Two locations, different numbers: an inbound message to location
    // A's number must never create data under location B, even when the
    // From number happens to coincidentally match an existing contact
    // there.
    public function test_inbound_to_location_as_number_never_creates_data_under_location_b(): void
    {
        $locationA = Location::factory()->create(['twilio_phone_number' => '+15551110000']);
        $locationB = Location::factory()->create(['twilio_phone_number' => '+15552220000']);

        $sharedFromNumber = '+15559876543';
        $contactInB = Contact::factory()->create([
            'location_id' => $locationB->id,
            'phone' => $sharedFromNumber,
            'first_name' => 'Existing B Contact',
        ]);

        $params = [
            'To' => '+15551110000',
            'From' => $sharedFromNumber,
            'Body' => 'Hi from a shared number',
            'MessageSid' => 'SM00000000000000000000000000005',
        ];

        $response = $this->withHeaders(['X-Twilio-Signature' => $this->signFor($this->webhookUrl(), $params)])
            ->post('/webhooks/twilio/sms', $params);

        $response->assertOk();

        $message = Message::withoutGlobalScopes()->sole();
        $this->assertSame($locationA->id, $message->location_id);

        // A new Contact was created under location A — B's contact with
        // the same phone number was never touched or reused.
        $contact = Contact::withoutGlobalScopes()->findOrFail($message->contact_id);
        $this->assertSame($locationA->id, $contact->location_id);
        $this->assertNotSame($contactInB->id, $contact->id);

        $this->assertSame(1, Contact::withoutGlobalScopes()->where('location_id', $locationB->id)->count());
        $this->assertSame(0, Message::withoutGlobalScopes()->where('location_id', $locationB->id)->count());
        $this->assertSame('Existing B Contact', $contactInB->fresh()->first_name);
    }
}
