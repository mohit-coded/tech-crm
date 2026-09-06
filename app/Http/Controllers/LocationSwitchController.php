<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class LocationSwitchController extends Controller
{
    public function switch(Location $location): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user->locations()->whereKey($location->id)->exists(), 403);

        $user->update(['current_location_id' => $location->id]);

        return back();
    }
}
