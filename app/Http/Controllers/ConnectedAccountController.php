<?php

namespace App\Http\Controllers;

use App\Models\ConnectedAccount;
use App\Services\Google\GoogleBusinessProfileClient;
use App\Services\OAuth\FacebookOAuthClient;
use App\Services\OAuth\GoogleOAuthClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Phase 9 Stage 1: OAuth connection infrastructure for Facebook and
 * Google. All three actions live in the 'auth' middleware group,
 * including callback() — see the routing note in routes/web.php for
 * why that's correct rather than assumed.
 *
 * disconnect() is route-model-bound against ConnectedAccount, which is
 * BelongsToLocation-scoped, so it's tenant-safe the same way every
 * other route-model-bound controller in this app is: a cross-tenant id
 * simply doesn't bind and 404s before the method body runs, no manual
 * check needed.
 *
 * Phase 9 Stage 3 added a Google-only side effect to callback(): a
 * best-effort auto-fill of the location's google_review_url from the
 * newly-connected Business Profile's primary location — see
 * autoFillGoogleReviewLinkIfMissing() below for the full reasoning.
 */
class ConnectedAccountController extends Controller
{
    /**
     * Redirects to the provider's OAuth consent screen. $provider is
     * constrained to 'facebook'/'google' at the route level (see
     * routes/web.php), so it's never anything else here.
     */
    public function connect(string $provider): RedirectResponse
    {
        // A random, unguessable value stored server-side (session) and
        // required back unchanged on callback — standard OAuth CSRF
        // protection. Without it, an attacker could start their own
        // authorization flow, capture the resulting code, and trick a
        // victim (already authenticated in our app) into visiting a
        // crafted callback URL with that code — completing an OAuth
        // exchange the victim never initiated and linking the
        // attacker's external account to the victim's location. Tying
        // the callback to a value this app itself generated and stored
        // *before* redirecting closes that: an attacker can't predict
        // or supply it. Generated here; verified in callback() below —
        // generating it alone would provide no protection at all.
        $state = Str::random(40);
        session([$this->stateSessionKey($provider) => $state]);

        $redirectUri = route('connected-accounts.callback', $provider);
        $authorizationUrl = $this->client($provider)->getAuthorizationUrl($redirectUri);

        return redirect()->away($this->withQueryParam($authorizationUrl, 'state', $state));
    }

    public function callback(string $provider, Request $request): RedirectResponse
    {
        $sessionKey = $this->stateSessionKey($provider);
        $expectedState = session($sessionKey);

        // Consumed immediately regardless of outcome — a state value is
        // only ever valid for the one callback it was generated for,
        // same one-time-use reasoning as a CSRF token.
        session()->forget($sessionKey);

        // Rejected with the same seriousness as a CSRF failure or the
        // Twilio webhook's signature check: no missing/mismatched state
        // is ever treated as "close enough". hash_equals() rather than
        // === for a timing-safe comparison, same reasoning as comparing
        // any other secret-ish token.
        abort_unless(
            is_string($expectedState) && hash_equals($expectedState, (string) $request->query('state')),
            403,
            'Invalid or missing OAuth state.'
        );

        $redirectUri = route('connected-accounts.callback', $provider);
        $tokenResult = $this->client($provider)->exchangeCodeForToken(
            (string) $request->query('code'),
            $redirectUri
        );

        // Explicit location_id, not BelongsToLocation's creating()
        // auto-fill: this route does have an authenticated user (see
        // the routing note in routes/web.php), but the upsert below
        // needs an explicit value in its own right, to match against
        // the unique (location_id, provider) constraint regardless of
        // whether auto-fill would also have supplied it.
        ConnectedAccount::updateOrCreate(
            [
                'location_id' => Auth::user()->current_location_id,
                'provider' => $provider,
            ],
            [
                'external_account_id' => $tokenResult->externalAccountId,
                'external_account_name' => $tokenResult->externalAccountName,
                'access_token' => $tokenResult->accessToken,
                'refresh_token' => $tokenResult->refreshToken,
                'expires_at' => $tokenResult->expiresAt,
                'connected_at' => now(),
            ]
        );

        if ($provider === 'google') {
            $this->autoFillGoogleReviewLinkIfMissing($tokenResult->accessToken);
        }

        return redirect()->route('settings.location.edit')->with('status', __('Account connected.'));
    }

    /**
     * Best-effort only, and Facebook's callback path never reaches this
     * at all (gated by the $provider === 'google' check above): fetches
     * the newly-connected account's primary Google Business Profile
     * location and, if it has a placeId, uses it to build a Google
     * review link — but ONLY when this location doesn't already have
     * one. Never overwrites a google_review_url the business owner
     * already set manually via the Settings page (Phase 7) — silently
     * clobbering a manually-entered value on every reconnect would be a
     * real, surprising bug (imagine an owner who deliberately set a
     * custom short link, or whose connected Business Profile changes to
     * a different location later — reconnecting shouldn't quietly
     * overwrite their choice).
     *
     * Failure here — no locations at all, or the Business Information
     * API call throwing for any reason — must never fail the OAuth
     * connection itself, which has already been saved by the time this
     * runs. The owner can always set google_review_url by hand via
     * Settings if this auto-fill doesn't pan out.
     */
    private function autoFillGoogleReviewLinkIfMissing(string $accessToken): void
    {
        $location = Auth::user()->currentLocation;

        if (! blank($location->google_review_url)) {
            return;
        }

        try {
            $primaryLocation = app(GoogleBusinessProfileClient::class)->fetchPrimaryLocation($accessToken);
        } catch (Throwable $e) {
            Log::warning('Failed to auto-fill Google review link from Business Profile', [
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if (! $primaryLocation || ! $primaryLocation->placeId) {
            return;
        }

        $location->update([
            'google_review_url' => 'https://search.google.com/local/writereview?placeid='.urlencode($primaryLocation->placeId),
        ]);
    }

    public function disconnect(ConnectedAccount $connectedAccount): RedirectResponse
    {
        $connectedAccount->delete();

        return redirect()->route('settings.location.edit')->with('status', __('Account disconnected.'));
    }

    private function client(string $provider): FacebookOAuthClient|GoogleOAuthClient
    {
        return $provider === 'facebook'
            ? app(FacebookOAuthClient::class)
            : app(GoogleOAuthClient::class);
    }

    private function stateSessionKey(string $provider): string
    {
        return "oauth_state.{$provider}";
    }

    /**
     * getAuthorizationUrl() deliberately takes no $state parameter (see
     * FacebookOAuthClient) — this app owns generating/verifying it, not
     * the client implementation, so it's appended to whatever URL the
     * client returns rather than threaded through the interface.
     */
    private function withQueryParam(string $url, string $key, string $value): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query([$key => $value]);
    }
}
