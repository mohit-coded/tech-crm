<?php

namespace App\Services\Google;

use Illuminate\Support\Facades\Http;

/**
 * Real GoogleBusinessProfileClient implementation, calling Google's
 * Business Profile APIs: My Business Account Management (to list the
 * connected accounts) followed by My Business Business Information (to
 * list a given account's locations).
 *
 * UNVERIFIED against a real Google Business Profile — no developer
 * credentials exist for this project yet. Same situation, and same
 * reasoning, as every other real *Impl class in this phase
 * (FacebookOAuthClientImpl/GoogleOAuthClientImpl in Stage 1,
 * FacebookLeadsClientImpl in Stage 2): built to Google's documented API
 * shape, never actually exercised against it. Tests never construct or
 * resolve this class — Tests\Fakes\FakeGoogleBusinessProfileClient is
 * bound in its place for every test that touches the Google OAuth
 * callback.
 *
 * Deliberate v1 simplification, not an oversight: uses the FIRST
 * account and FIRST location returned, with no multi-account/
 * multi-location picker UI anywhere in this app. Most businesses using
 * this CRM are expected to have exactly one Google Business Profile
 * account managing exactly one physical location — building a picker
 * for the agency-managing-many-locations case is real but out of scope
 * until this app's usage actually needs it. Revisit if/when it does.
 */
class GoogleBusinessProfileClientImpl implements GoogleBusinessProfileClient
{
    private const ACCOUNTS_URL = 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts';

    private const LOCATIONS_URL_TEMPLATE = 'https://mybusinessbusinessinformation.googleapis.com/v1/%s/locations';

    public function fetchPrimaryLocation(string $accessToken): ?GoogleLocationData
    {
        $accounts = Http::withToken($accessToken)
            ->get(self::ACCOUNTS_URL)
            ->throw()
            ->json('accounts', []);

        if (empty($accounts)) {
            return null;
        }

        $accountName = $accounts[0]['name'];

        $locations = Http::withToken($accessToken)
            ->get(sprintf(self::LOCATIONS_URL_TEMPLATE, $accountName), [
                'readMask' => 'name,title,metadata',
            ])
            ->throw()
            ->json('locations', []);

        if (empty($locations)) {
            return null;
        }

        $location = $locations[0];

        return new GoogleLocationData(
            placeId: (string) ($location['metadata']['placeId'] ?? ''),
            locationName: (string) ($location['title'] ?? ''),
        );
    }
}
