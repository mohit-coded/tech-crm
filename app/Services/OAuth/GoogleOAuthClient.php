<?php

namespace App\Services\OAuth;

/**
 * Injectable contract for the Google OAuth flow — see
 * FacebookOAuthClient for the full reasoning (same template, kept as a
 * separate interface per provider rather than one shared one, since
 * Facebook and Google's real token/profile responses already differ
 * and are free to diverge further without either interface needing to
 * accommodate the other).
 */
interface GoogleOAuthClient
{
    public function getAuthorizationUrl(string $redirectUri): string;

    public function exchangeCodeForToken(string $code, string $redirectUri): OAuthTokenResult;
}
