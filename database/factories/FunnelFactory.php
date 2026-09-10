<?php

namespace Database\Factories;

use App\Models\Funnel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Funnel>
 */
class FunnelFactory extends Factory
{
    protected $model = Funnel::class;

    /**
     * Define the model's default state.
     *
     * location_id is deliberately omitted (like ContactFactory) so
     * BelongsToLocation's creating() hook auto-fills it from the acting
     * user's current_location_id in tests that rely on that; tests
     * without an authenticated user (e.g. public funnel submission)
     * must pass location_id explicitly.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'slug' => Str::slug(fake()->unique()->words(4, true)),
            'headline' => fake()->sentence(),
            'subheadline' => fake()->sentence(),
            'button_text' => 'Claim Offer',
            'is_published' => false,
        ];
    }
}
