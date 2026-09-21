<?php

namespace App\Services\OAuth;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Real GoogleOAuthClient implementation, built to the standard Google
 * OAuth 2.0 (web server flow) shape.
 *
 * UNVERIFIED against a real Google Cloud OAuth client — no credentials
 * exist for this project yet (services.google_business.* in
 * config/services.php are placeholders from .env.example only). Same
 * situation as FacebookOAuthClientImpl — see that class's docblock for
 * the full reasoning; it applies here identically. Tests never
 * construct or resolve this class: Tests\Fakes\FakeGoogleOAuthClient is
 * bound in its place for every test that touches the OAuth flow.
 */
class GoogleOAuthClientImpl implements GoogleOAuthClient
{
    private const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v2/userinfo';

    public function getAuthorizationUrl(string $redirectUri): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => config('services.google_business.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/business.manage',
            // Google only returns a refresh_token on the FIRST consent,
            // unless explicitly forced — required for the offline access
            // this integration needs (renewing without the user present).
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);
    }

    public function exchangeCodeForToken(string $code, string $redirectUri): OAuthTokenResult
    {
        $token = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google_business.client_id'),
            'client_secret' => config('services.google_business.client_secret'),
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
            'code' => $code,
        ])->throw()->json();

        $profile = Http::withToken($token['access_token'])
            ->get(self::USERINFO_URL)
            ->throw()
            ->json();

        return new OAuthTokenResult(
            accessToken: $token['access_token'],
            refreshToken: $token['refresh_token'] ?? null,
            expiresAt: isset($token['expires_in'])
                ? Carbon::now()->addSeconds((int) $token['expires_in'])
                : null,
            externalAccountId: (string) $profile['id'],
            externalAccountName: (string) ($profile['name'] ?? $profile['email'] ?? $profile['id']),
        );
    }
}
