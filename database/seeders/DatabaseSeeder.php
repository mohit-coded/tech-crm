<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $location = Location::factory()->create([
            'name' => 'Test Location',
        ]);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'current_location_id' => $location->id,
        ]);

        $user->locations()->attach($location->id, ['role' => 'owner']);

        $pipeline = Pipeline::factory()->create([
            'location_id' => $location->id,
            'name' => 'Default Pipeline',
            'is_default' => true,
        ]);

        collect([
            'New Leads',
            'Hot Leads',
            'Booking Requested',
            'Booking Confirmed',
            'Service(s) Sold',
        ])->each(fn (string $name, int $position) => $pipeline->stages()->create([
            'name' => $name,
            'position' => $position,
        ]));
    }
}
