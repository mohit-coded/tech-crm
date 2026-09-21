<?php

namespace App\Services\OAuth;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Real FacebookOAuthClient implementation, built to the standard
 * Facebook Login / Graph API OAuth flow shape.
 *
 * UNVERIFIED against a real Facebook Developer App — no credentials
 * exist for this project yet (services.facebook_ads.* in
 * config/services.php are placeholders from .env.example only, same
 * situation Twilio was in during Phase 5a). Tests never construct or
 * resolve this class at all: Tests\Fakes\FakeFacebookOAuthClient is
 * bound in its place for every test that touches the OAuth flow, so no
 * test can accidentally make a live call to Facebook. This class proves
 * out the flow's *shape* (matching Facebook's documented endpoints) but
 * has not actually completed a real OAuth round trip — revisit and
 * verify once a real Facebook Developer App exists, same as Twilio's
 * own "unverified" note in Phase 5a.
 */
class FacebookOAuthClientImpl implements FacebookOAuthClient
{
    private const AUTHORIZE_URL = 'https://www.facebook.com/v19.0/dialog/oauth';

    private const TOKEN_URL = 'https://graph.facebook.com/v19.0/oauth/access_token';

    private const ME_URL = 'https://graph.facebook.com/v19.0/me';

    public function getAuthorizationUrl(string $redirectUri): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => config('services.facebook_ads.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'ads_management,pages_show_list,business_management',
        ]);
    }

    public function exchangeCodeForToken(string $code, string $redirectUri): OAuthTokenResult
    {
        $token = Http::get(self::TOKEN_URL, [
            'client_id' => config('services.facebook_ads.client_id'),
            'client_secret' => config('services.facebook_ads.client_secret'),
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ])->throw()->json();

        $profile = Http::get(self::ME_URL, [
            'access_token' => $token['access_token'],
            'fields' => 'id,name',
        ])->throw()->json();

        return new OAuthTokenResult(
            accessToken: $token['access_token'],
            // Facebook doesn't issue a separate refresh token the way
            // Google does — long-lived tokens are obtained by exchanging
            // a short-lived one for a long-lived one via the same token
            // endpoint, not via a refresh_token grant. Out of scope for
            // this stage.
            refreshToken: null,
            expiresAt: isset($token['expires_in'])
                ? Carbon::now()->addSeconds((int) $token['expires_in'])
                : null,
            externalAccountId: (string) $profile['id'],
            externalAccountName: (string) $profile['name'],
        );
    }
}
