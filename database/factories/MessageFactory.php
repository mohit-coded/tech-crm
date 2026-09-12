<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * location_id is deliberately omitted (like ContactFactory/CalendarFactory)
     * so BelongsToLocation's creating() hook auto-fills it from the acting
     * user's current_location_id in tests that rely on that. Queued-job
     * tests (no authenticated user) must pass it explicitly.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'direction' => 'outbound',
            'body' => fake()->sentence(),
            'twilio_sid' => null,
            'status' => 'queued',
        ];
    }
}
