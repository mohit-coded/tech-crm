<?php

namespace App\Services\Facebook;

/**
 * Injectable contract for fetching a Facebook Lead Ad submission's
 * actual field data — deliberately not a static facade, so a fake
 * implementation can be bound in the container for tests instead of
 * ever making a real Graph API call. Same template as SmsSender/
 * FacebookOAuthClient (see AppServiceProvider).
 */
interface FacebookLeadsClient
{
    /**
     * $accessToken is the connected Page's own access token (stored on
     * ConnectedAccount from the OAuth flow in Phase 9 Stage 1) — Lead
     * Ads data can only be read with a token for the Page the form
     * belongs to, not an arbitrary user token.
     */
    public function fetchLead(string $leadgenId, string $accessToken): FacebookLeadData;
}
