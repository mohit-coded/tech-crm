<?php

namespace Tests\Fakes;

use App\Services\OAuth\FacebookOAuthClient;
use App\Services\OAuth\OAuthTokenResult;
use Illuminate\Support\Carbon;

/**
 * Test double for FacebookOAuthClient — bound in the container in
 * place of FacebookOAuthClientImpl so tests never make a real Facebook
 * API call. Records every call made to it so tests can assert what was
 * sent.
 */
class FakeFacebookOAuthClient implements FacebookOAuthClient
{
    /** @var list<string> */
    public array $authorizationUrlRedirectUris = [];

    /** @var list<array{code: string, redirect_uri: string}> */
    public array $exchangeCalls = [];

    public function __construct(
        public string $authorizationUrl = 'https://facebook.example.test/oauth/authorize?client_id=fake',
        public string $externalAccountId = 'fb-account-123',
        public string $externalAccountName = 'Fake Facebook Page',
        public string $accessToken = 'fake-facebook-access-token',
        public ?string $refreshToken = null,
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
            expiresAt: Carbon::now()->addDays(60),
            externalAccountId: $this->externalAccountId,
            externalAccountName: $this->externalAccountName,
        );
    }
}
