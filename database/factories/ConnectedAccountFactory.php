<?php

namespace Database\Factories;

use App\Models\ConnectedAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ConnectedAccount>
 */
class ConnectedAccountFactory extends Factory
{
    protected $model = ConnectedAccount::class;

    /**
     * location_id is deliberately omitted (like ContactFactory/CalendarFactory)
     * so BelongsToLocation's creating() hook auto-fills it from the acting
     * user's current_location_id in tests that rely on that.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'facebook',
            'external_account_id' => (string) fake()->numerify('##########'),
            'external_account_name' => fake()->company(),
            'access_token' => Str::random(40),
            'refresh_token' => null,
            'expires_at' => null,
            'connected_at' => now(),
        ];
    }

    public function google(): self
    {
        return $this->state(['provider' => 'google']);
    }
}
