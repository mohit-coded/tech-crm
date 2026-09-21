<?php

namespace App\Services\OAuth;

use Carbon\Carbon;

/**
 * The outcome of a successful *OAuthClient::exchangeCodeForToken() call.
 * There's no failure variant (unlike SmsSendResult) — a failed exchange
 * is expected to throw, since there's no sensible partial/"failed"
 * ConnectedAccount to build from a token exchange that didn't produce a
 * token.
 */
final class OAuthTokenResult
{
    public function __construct(
        public readonly string $accessToken,
        public readonly ?string $refreshToken,
        public readonly ?Carbon $expiresAt,
        public readonly string $externalAccountId,
        public readonly string $externalAccountName,
    ) {
    }
}
