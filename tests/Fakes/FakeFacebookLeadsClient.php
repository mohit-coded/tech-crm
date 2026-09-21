<?php

namespace Tests\Fakes;

use App\Services\Facebook\FacebookLeadData;
use App\Services\Facebook\FacebookLeadsClient;

/**
 * Test double for FacebookLeadsClient — bound in the container in
 * place of FacebookLeadsClientImpl so tests never make a real Graph
 * API call. Returns canned FacebookLeadData keyed by leadgenId, set up
 * by the test via willReturn(), and records every call made to it so
 * tests can assert what was requested (including the access token
 * used).
 */
class FakeFacebookLeadsClient implements FacebookLeadsClient
{
    /** @var array<string, FacebookLeadData> */
    private array $leadsByLeadgenId = [];

    /** @var list<array{leadgen_id: string, access_token: string}> */
    public array $calls = [];

    public function willReturn(string $leadgenId, FacebookLeadData $lead): void
    {
        $this->leadsByLeadgenId[$leadgenId] = $lead;
    }

    public function fetchLead(string $leadgenId, string $accessToken): FacebookLeadData
    {
        $this->calls[] = ['leadgen_id' => $leadgenId, 'access_token' => $accessToken];

        return $this->leadsByLeadgenId[$leadgenId] ?? new FacebookLeadData(
            leadgenId: $leadgenId,
            name: 'Fake Lead',
            email: 'fake-lead@example.com',
            phone: '+15550001111',
            pageId: 'fake-page-id',
        );
    }
}
