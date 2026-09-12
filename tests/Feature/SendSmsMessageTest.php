<?php

namespace Tests\Feature;

use App\Jobs\SendSmsMessage;
use App\Models\Contact;
use App\Models\Location;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

/**
 * Calls SendSmsMessage::handle() directly with a fake SmsSender — no
 * queue worker, no real Twilio API call. This is the "no authenticated
 * user" case: location_id is set explicitly on the Message/Contact
 * below rather than relying on BelongsToLocation's auto-fill.
 */
class SendSmsMessageTest extends TestCase
{
    use RefreshDatabase;

    private function makeQueuedMessage(): Message
    {
        $location = Location::factory()->create();
        $contact = Contact::factory()->create([
            'location_id' => $location->id,
            'phone' => '+15555550123',
        ]);

        return Message::factory()->create([
            'location_id' => $location->id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'body' => 'Your appointment is confirmed.',
            'status' => 'queued',
        ]);
    }

    public function test_successful_send_updates_status_and_twilio_sid(): void
    {
        $message = $this->makeQueuedMessage();
        $sender = new FakeSmsSender(shouldSucceed: true, sid: 'SM1234567890');

        (new SendSmsMessage($message))->handle($sender);

        $message->refresh();
        $this->assertSame('sent', $message->status);
        $this->assertSame('SM1234567890', $message->twilio_sid);

        $this->assertSame([['to' => '+15555550123', 'body' => 'Your appointment is confirmed.']], $sender->calls);
    }

    public function test_failed_send_marks_the_message_failed_and_logs_the_error_instead_of_throwing(): void
    {
        Log::spy();

        $message = $this->makeQueuedMessage();
        $sender = new FakeSmsSender(shouldSucceed: false, errorMessage: 'The number is not a valid phone number.');

        (new SendSmsMessage($message))->handle($sender);

        $message->refresh();
        $this->assertSame('failed', $message->status);
        $this->assertNull($message->twilio_sid);

        Log::shouldHaveReceived('error')->once()->withArgs(
            fn (string $logMessage, array $context) => $logMessage === 'SMS send failed'
                && $context['message_id'] === $message->id
                && $context['error'] === 'The number is not a valid phone number.'
        );
    }
}
