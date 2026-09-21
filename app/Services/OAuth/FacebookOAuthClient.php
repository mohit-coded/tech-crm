<?php

namespace App\Services\OAuth;

/**
 * Injectable contract for the Facebook OAuth flow — deliberately not a
 * static facade, so a fake implementation can be bound in the
 * container for tests instead of ever making a real network call. Same
 * template as SmsSender/TwilioSmsSender (see AppServiceProvider).
 */
interface FacebookOAuthClient
{
    /**
     * The URL to send the user's browser to for Facebook's consent
     * screen. Deliberately takes no $state parameter — the caller
     * (ConnectedAccountController::connect()) owns generating and
     * appending the CSRF-protection state value itself, since it's also
     * the one that has to verify it again on callback.
     */
    public function getAuthorizationUrl(string $redirectUri): string;

    /**
     * Exchanges an authorization $code (from the callback query string)
     * for a real access/refresh token and the connected external
     * account's id/name, using the exact same $redirectUri that was
     * used to obtain the code (required by the OAuth spec — the token
     * endpoint verifies it matches).
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): OAuthTokenResult;
}
