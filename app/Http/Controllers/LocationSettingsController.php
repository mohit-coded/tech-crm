<?php

namespace App\Http\Controllers;

use App\Models\ConnectedAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * One settings page per location, not a list — no {location} route
 * parameter, so there's structurally no id to smuggle: edit()/update()
 * always act on Auth::user()->currentLocation, the same tenant-scoping
 * source of truth BelongsToLocation itself keys off (current_location_id).
 * A user can only ever affect the one location they're currently acting
 * in, same as every other implicit-tenant-scoping controller in this
 * app, just without route-model binding since there's no id in the URL.
 *
 * Deliberately minimal on the editable-fields front: only
 * google_review_url is a form field here, since that's the one Location
 * field Phase 7 Stage 2 needed. Location has other fillable fields
 * (phone, timezone, etc.) with no admin UI of their own yet, but
 * expanding this page to cover them is out of scope here — same
 * incremental-build approach as AvailabilitySlotCalculator/Campaigns:
 * build what the current phase needs first. The "Connected Accounts"
 * section added in Phase 9 Stage 1 lives on this same page rather than
 * a separate one — connect/disconnect are single-click actions handled
 * by ConnectedAccountController, not additional fields on this
 * controller's own form.
 */
class LocationSettingsController extends Controller
{
    public function edit(): View
    {
        return view('settings.location', [
            'location' => Auth::user()->currentLocation,
            // ConnectedAccount is BelongsToLocation-scoped, so this is
            // already filtered to the current location — no manual
            // location_id filtering needed, same as everywhere else.
            'connectedAccounts' => ConnectedAccount::all()->keyBy('provider'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'google_review_url' => ['nullable', 'url', 'max:2048'],
        ]);

        Auth::user()->currentLocation->update($validated);

        return redirect()->route('settings.location.edit')->with('status', __('Settings updated.'));
    }
}
