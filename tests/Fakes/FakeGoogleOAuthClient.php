<?php

namespace Tests\Fakes;

use App\Services\OAuth\GoogleOAuthClient;
use App\Services\OAuth\OAuthTokenResult;
use Illuminate\Support\Carbon;

/**
 * Test double for GoogleOAuthClient — bound in the container in place
 * of GoogleOAuthClientImpl so tests never make a real Google API call.
 * Records every call made to it so tests can assert what was sent.
 */
class FakeGoogleOAuthClient implements GoogleOAuthClient
{
    /** @var list<string> */
    public array $authorizationUrlRedirectUris = [];

    /** @var list<array{code: string, redirect_uri: string}> */
    public array $exchangeCalls = [];

    public function __construct(
        public string $authorizationUrl = 'https://google.example.test/o/oauth2/auth?client_id=fake',
        public string $externalAccountId = 'google-account-456',
        public string $externalAccountName = 'Fake Google Business',
        public string $accessToken = 'fake-google-access-token',
        public ?string $refreshToken = 'fake-google-refresh-token',
    ) {
    }

    public function getAuthorizationUrl(string $redirectUri): string
    {
        $this->authorizationUrlRedirectUris[] = $redirectUri;

        return $this->authorizationUrl;
    }

    public function exchangeCodeForToken(string $code, string $redirectUri): OAuthTokenResult
    {
        $this->exchangeCalls[] = ['code' => $code, 'redirect_uri' => $redirectUri];

        return new OAuthTokenResult(
            accessToken: $this->accessToken,
            refreshToken: $this->refreshToken,
            expiresAt: Carbon::now()->addHour(),
            externalAccountId: $this->externalAccountId,
            externalAccountName: $this->externalAccountName,
        );
    }
}
