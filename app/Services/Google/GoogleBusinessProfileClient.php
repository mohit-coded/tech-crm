<?php

namespace App\Services\Google;

/**
 * Injectable contract for reading a connected Google account's Business
 * Profile location(s) — deliberately not a static facade, so a fake
 * implementation can be bound in the container for tests instead of
 * ever making a real network call. Same template as SmsSender/
 * FacebookOAuthClient/FacebookLeadsClient (see AppServiceProvider).
 */
interface GoogleBusinessProfileClient
{
    /**
     * Returns null if the account has no Business Profile locations at
     * all. See GoogleBusinessProfileClientImpl for why this is always
     * the FIRST location of the FIRST account, never a choice among
     * several.
     */
    public function fetchPrimaryLocation(string $accessToken): ?GoogleLocationData;
}
